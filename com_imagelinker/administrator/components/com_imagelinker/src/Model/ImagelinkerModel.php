<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Imagelinker
 * @version     1.5
 * @license     GNU General Public License version 2
 */

namespace Naftee\Component\Imagelinker\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\Component\ComponentHelper;

class ImagelinkerModel extends FormModel
{

    /**
     * Scans for unlinked images in selected media folders.
     */
    public function scanForUnlinkedImages(array $selectedFolders = []): array
    {
        $allMediaImages = [];

        foreach ($selectedFolders as $folder) {

            $fullPath = JPATH_ROOT . '/' . $folder;
            if (!is_dir($fullPath)) {
                continue;
            }

            foreach (Folder::files($fullPath, '.', false, true) as $fullFilePath) {
                //Only accept images in the array 
                if (!MediaHelper::isImage($fullFilePath)) {
                    continue;
                }

                $relativeFilePath = substr($fullFilePath,strlen(JPATH_ROOT));

                $allMediaImages[] = $relativeFilePath;
            }
        }

        $referencedImages = $this->getReferencedImages();

        // Extract ONLY the image paths from the associative referenced array.
        $referencedPaths = array_keys($referencedImages);

        /*
         * Build the data structure map containing paths, their link status, 
         * and granular database tracking details.
         */
        $processedImages = [];

        foreach ($allMediaImages as $imagePath) {
            $isLinked = in_array($imagePath, $referencedPaths, true);

            $processedImages[] = [
                'path'       => $imagePath,
                'status'     => $isLinked ? 'linked' : 'unlinked',
                'references' => $isLinked ? $referencedImages[$imagePath] : []
            ];
        }

        /*
         * Locate the unused images returning the array_diff result to maintain backward 
         */
        $unusedImages = array_values(
            array_diff(
                $allMediaImages,
                $referencedPaths
            )
        );
        return [
          'unlinked_images' => $unusedImages,
          'processed_images'    => $processedImages,
        ];
    
    }

    /**
     * Scans various database tables for image references.
     *
     * @return  array  Associative array of image paths relative to JPATH_ROOT, 
     *                 each containing an array of locations where it is referenced.
     */
    protected function getReferencedImages(): array
    {
        $referencedImages = [];
        
        // Get the configuration option for more permissive image scanning inside content
        $params = ComponentHelper::getParams('com_imagelinker');
        $onlyImg = (bool) $params->get('only_img', 1);

        // Create a Joomla database object using the updated version of $this->getDbo
        try {
            $db = $this->getDatabase();
        } catch (\Exception $e) {
            $this->setError(
                Text::sprintf(
                    'JLIB_DATABASE_ERROR_CONNECT_DATABASE',
                    $e->getMessage()
                )
            );

            return [];
        }

        // 1. Scan #__content (articles)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'introtext', 'fulltext', 'images']))
                ->from($db->quoteName('#__content'));

            $db->setQuery($query);
            $articles = $db->loadObjectList();

            foreach ($articles as $article) {
                // Scan introtext and fulltext for <img> tags
                $texts = [
                    'introtext' => $article->introtext,
                    'fulltext'  => $article->fulltext
                ];

                foreach ($texts as $fieldName => $text) {

                    $imagePaths = $this->extractImagePaths(
                        (string) $text,
                        $onlyImg
                    );

                    foreach ($imagePaths as $src) {

                        $this->addReferencedImage(
                            $src,
                            [
                                'table' => '#__content',
                                'id'    => (int) $article->id,
                                'field' => $fieldName
                            ],
                            $referencedImages
                        );
                    }
                }

                // Parse images field (JSON)
                $imagesField = json_decode((string) $article->images, true);

                if (is_array($imagesField)) {
                    foreach (['image_intro', 'image_fulltext'] as $field) {
                        if (!empty($imagesField[$field])) {
                            $this->addReferencedImage(
                                $imagesField[$field],
                                [
                                    'table' => '#__content',
                                    'id'    => (int) $article->id,
                                    'field' => 'images.' . $field
                                ],
                                $referencedImages
                            );
                        }
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $this->setError(
                Text::sprintf(
                    'JLIB_DATABASE_QUERY_FAILED',
                    '#__content',
                    $e->getMessage()
                )
            );
        }

        // 2. Scan #__modules (for Custom HTML module content)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'content', 'params']))
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_custom'));

            $db->setQuery($query);
            $modules = $db->loadObjectList();

            foreach ($modules as $module) {
                $imagePaths = $this->extractImagePaths(
                (string) $module->content,
                $onlyImg
            );

            foreach ($imagePaths as $src) {

                $this->addReferencedImage(
                    $src,
                    [
                        'table' => '#__modules',
                        'id'    => (int) $module->id,
                        'field' => 'content'
                    ],
                    $referencedImages
                );
            }

                // Parse params field for module background image
                $params = json_decode((string) $module->params, true);

                if (is_array($params) && !empty($params['backgroundimage'])) {
                    $this->addReferencedImage(
                        $params['backgroundimage'],
                        [
                            'table' => '#__modules',
                            'id'    => (int) $module->id,
                            'field' => 'params.backgroundimage'
                        ],
                        $referencedImages
                    );
                }
            }
        } catch (\RuntimeException $e) {
            $this->setError(
                Text::sprintf(
                    'JLIB_DATABASE_QUERY_FAILED',
                    '#__modules',
                    $e->getMessage()
                )
            );
        }

        // 3. Scan #__categories (image fields)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'params']))
                ->from($db->quoteName('#__categories'));

            $db->setQuery($query);
            $categories = $db->loadObjectList();

            foreach ($categories as $category) {
                $params = json_decode((string) $category->params, true);

                if (is_array($params) && !empty($params['image'])) {
                    $this->addReferencedImage(
                        $params['image'],
                        [
                            'table' => '#__categories',
                            'id'    => (int) $category->id,
                            'field' => 'params.image'
                        ],
                        $referencedImages
                    );
                }
            }
        } catch (\RuntimeException $e) {
            $this->setError(
                Text::sprintf(
                    'JLIB_DATABASE_QUERY_FAILED',
                    '#__categories',
                    $e->getMessage()
                )
            );
        }

        // 4. Scan #__contact_details (intro images)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'image']))
                ->from($db->quoteName('#__contact_details'));

            $db->setQuery($query);
            $contacts = $db->loadObjectList();

            foreach ($contacts as $contact) {
                if (!empty($contact->image)) {
                    $this->addReferencedImage(
                        $contact->image,
                        [
                            'table' => '#__contact_details',
                            'id'    => (int) $contact->id,
                            'field' => 'image'
                        ],
                        $referencedImages
                    );
                }
            }
        } catch (\RuntimeException $e) {
            $this->setError(
                Text::sprintf(
                    'JLIB_DATABASE_QUERY_FAILED',
                    '#__contact_details',
                    $e->getMessage()
                )
            );
        }

        // 5. Scan #__banners (banner images)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'params']))
                ->from($db->quoteName('#__banners'));

            $db->setQuery($query);
            $banners = $db->loadObjectList();

            foreach ($banners as $banner) {
                $params = json_decode((string) $banner->params, true);

                if (is_array($params) && !empty($params['imageurl'])) {
                    $this->addReferencedImage(
                        $params['imageurl'],
                        [
                            'table' => '#__banners',
                            'id'    => (int) $banner->id,
                            'field' => 'params.imageurl'
                        ],
                        $referencedImages
                    );
                }
            }
        } catch (\RuntimeException $e) {
            $this->setError(
                Text::sprintf(
                    'JLIB_DATABASE_QUERY_FAILED',
                    '#__banners',
                    $e->getMessage()
                )
            );
        }

        // 6. Scan #__newsfeeds (image fields)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'images']))
                ->from($db->quoteName('#__newsfeeds'));

            $db->setQuery($query);
            $newsfeeds = $db->loadObjectList();

            foreach ($newsfeeds as $newsfeed) {
                $imagesField = json_decode((string) $newsfeed->images, true);

                if (is_array($imagesField)) {
                    foreach (['image_first', 'image_second'] as $field) {
                        if (!empty($imagesField[$field])) {
                            $this->addReferencedImage(
                                $imagesField[$field],
                                [
                                    'table' => '#__newsfeeds',
                                    'id'    => (int) $newsfeed->id,
                                    'field' => 'images.' . $field
                                ],
                                $referencedImages
                            );
                        }
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $this->setError(
                Text::sprintf(
                    'JLIB_DATABASE_QUERY_FAILED',
                    '#__newsfeeds',
                    $e->getMessage()
                )
            );
        }

        // 7. Scan #__menu (menu item images)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'params']))
                ->from($db->quoteName('#__menu'));

            $db->setQuery($query);
            $menuItems = $db->loadObjectList();

            foreach ($menuItems as $menuItem) {
                $params = json_decode((string) $menuItem->params, true);

                if (is_array($params)) {
                    foreach ($params as $key => $value) {
                        if (str_ends_with($key, '_image') && !empty($value)) {
                            $this->addReferencedImage(
                                $value,
                                [
                                    'table' => '#__menu',
                                    'id'    => (int) $menuItem->id,
                                    'field' => 'params.' . $key
                                ],
                                $referencedImages
                            );
                        }
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $this->setError(
                Text::sprintf(
                    'JLIB_DATABASE_QUERY_FAILED',
                    '#__menu',
                    $e->getMessage()
                )
            );
        }

        // 8. Scan #__tags (tag images)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'images']))
                ->from($db->quoteName('#__tags'));

            $db->setQuery($query);
            $tags = $db->loadObjectList();

            foreach ($tags as $tag) {
                $imagesField = json_decode((string) $tag->images, true);

                if (is_array($imagesField)) {
                    foreach (['image_intro', 'image_fulltext'] as $field) {
                        if (!empty($imagesField[$field])) {
                            $this->addReferencedImage(
                                $imagesField[$field],
                                [
                                    'table' => '#__tags',
                                    'id'    => (int) $tag->id,
                                    'field' => 'images.' . $field
                                ],
                                $referencedImages
                            );
                        }
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $this->setError(
                Text::sprintf(
                    'JLIB_DATABASE_QUERY_FAILED',
                    '#__tags',
                    $e->getMessage()
                )
            );
        }

        // 9. Scan #__users (for user profile images in params)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'params']))
                ->from($db->quoteName('#__users'));

            $db->setQuery($query);
            $users = $db->loadObjectList();

            foreach ($users as $user) {
                $params = json_decode((string) $user->params, true);

                if (is_array($params) && !empty($params['profile_image'])) {
                    $this->addReferencedImage(
                        $params['profile_image'],
                        [
                            'table' => '#__users',
                            'id'    => (int) $user->id,
                            'field' => 'params.profile_image'
                        ],
                        $referencedImages
                    );
                }
            }
        } catch (\RuntimeException $e) {
            $this->setError(
                Text::sprintf(
                    'JLIB_DATABASE_QUERY_FAILED',
                    '#__users',
                    $e->getMessage()
                )
            );
        }

        // 10. Scan #__template_styles (template logo images)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'params']))
                ->from($db->quoteName('#__template_styles'));

            $db->setQuery($query);
            $templates = $db->loadObjectList();

            foreach ($templates as $template) {
                $params = json_decode((string) $template->params, true);

                if (is_array($params) && !empty($params['logoFile'])) {
                    $this->addReferencedImage(
                        $params['logoFile'],
                        [
                            'table' => '#__template_styles',
                            'id'    => (int) $template->id,
                            'field' => 'params.logoFile'
                        ],
                        $referencedImages
                    );
                }
            }
        } catch (\RuntimeException $e) {
            $this->setError(
                Text::sprintf(
                    'JLIB_DATABASE_QUERY_FAILED',
                    '#__template_styles',
                    $e->getMessage()
                )
            );
        }

        // 11. Scan #__schemaorg (Joomla 5 Schema.org data)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'schema']))
                ->from($db->quoteName('#__schemaorg'));

            $db->setQuery($query);
            $schemaItems = $db->loadObjectList();

            foreach ($schemaItems as $item) {
                $schemaData = json_decode((string) $item->schema, true);

                if (is_array($schemaData) && !empty($schemaData['image'])) {
                    $imagePath = $schemaData['image'];
                    $cleanedPath = strtok($imagePath, '#');

                    if ($cleanedPath) {
                        $this->addReferencedImage(
                            $cleanedPath,
                            [
                                'table' => '#__schemaorg',
                                'id'    => (int) $item->id,
                                'field' => 'schema.image'
                            ],
                            $referencedImages
                        );
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $this->setError(
                Text::sprintf(
                    'JLIB_DATABASE_QUERY_FAILED',
                    '#__schemaorg',
                    $e->getMessage()
                )
            );
        }

        return $referencedImages;
    }
    
    /**
     * Extract image references from text content.
     *
     * @param   string  $text      The content to scan.
     * @param   bool    $onlyImg   True = scan only <img src="">
     *
     * @return  array
     */
      protected function extractImagePaths(string $text, bool $onlyImg = true): array
      {
          if (trim($text) === '') {
              return [];
          }

          // Path 1: Only scan <img src="">
          if ($onlyImg) {
              preg_match_all('/<img[^>]+src=["\'](.*?)["\']/i', $text, $matches);
              
              return array_unique($matches[1] ?? []);
          }

          // Path 2: Scan all image references in text (href, popups, etc.)
          preg_match_all('/images\/[a-zA-Z0-9_\-\s\/\.%]+?\.(jpg|jpeg|png|gif|webp|svg|avif)/i', $text, $matches);

          // If no matches, $matches[0] is an empty array anyway, so we can clean this up
          return array_unique($matches[0] ?? []);
      }

    /**
     * Cleans and adds an image source to the referenced images array, along with its location.
     * 
     * @param string $src               The source path or URL of the image.
     * @param array  $location          An associative array containing 'table', 'id', and 'field'.
     * @param array  &$referencedImages The reference array being populated.
     */
    protected function addReferencedImage(string $src, array $location, array &$referencedImages): void
    {
        $cleanSrc = strtok($src, '#'); // Remove any fragments from the URL, such as #joomlaImage
        $root = Uri::root();
        
        // Handle valid image URLs
        if (filter_var($cleanSrc, FILTER_VALIDATE_URL)) {
           if (!str_starts_with($cleanSrc, $root)) {
              return; // If $cleanSrc is a valid URL but not from this site, reject it
           }

           $cleanSrc = substr($cleanSrc, strlen($root)); // Strip the root to make it a relative path
        }
        
        $cleanSrc = Path::clean('/' . ltrim($cleanSrc, '/'));
        
        // Only keep actual image files
        $fullPath = JPATH_ROOT . $cleanSrc;
        if (!MediaHelper::isImage($fullPath)) {
            return;
        }
        
        // Initialize the array for this image path if it doesn't exist
        if (!isset($referencedImages[$cleanSrc])) {
            $referencedImages[$cleanSrc] = [];
        }
        
        // Prevent duplicate location entries for the exact same image and placement
        if (!in_array($location, $referencedImages[$cleanSrc], true)) {
            $referencedImages[$cleanSrc][] = $location;
        }
    }

    /**
     * Deletes a single image from the filesystem.
     */
    public function deleteUnlinkedImage(string $imagePath): bool
    {
        try {

            $fullPath = Path::clean(
                JPATH_ROOT . '/'
                . ltrim($imagePath, '/')
            );

            if (!File::exists($fullPath)) {

                $this->setError(
                    Text::sprintf(
                        'COM_IMAGELINKER_FILE_NOT_FOUND',
                        htmlspecialchars(
                            $imagePath,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    )
                );

                return false;
            }

            // Attempt to delete the file
            if (!File::delete($fullPath)) {

                $this->setError(
                    Text::sprintf(
                        'COM_IMAGELINKER_DELETE_FAILED',
                        htmlspecialchars(
                            $imagePath,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    )
                );

                return false;
            }

            return true;

        } catch (\Exception $e) {

            $this->setError(
                Text::_(
                    'COM_IMAGELINKER_DELETE_ERROR'
                ) . ': ' . $e->getMessage()
            );

            return false;
        }
    }

    /**
     * Method to get the record form.
     */
    public function getForm($data = [], $loadData = true): Form|false
       {    
       $form = $this->loadForm(
          'com_imagelinker.imagelinker',   // unique name to identify the form
          'imagelinker',                   // source filename (looks for imagelinker.xml in /forms )
           array('control' => 'jform',     // Array name for POST parameters
	   	           'load_data' => $loadData) // set to TRUE to initiate the callback to loadFormData 
           );
	
      return $form;
      }      

    /**
     * Method to get the data that should be injected in the form.
     */
    protected function loadFormData(): mixed
       {
       $app = Factory::getApplication();
       $data = [];

       // Check if the user state has data (e.g. in the case of a failed form submission),
       // and pre-load it for the form
       $userStateData = $app->getUserState('com_imagelinker.edit.imagelinker.data', []);

       if (!empty($userStateData)) 
          {
          $data = $userStateData;
          }

       return $data;
       }
}