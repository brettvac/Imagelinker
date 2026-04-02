<?php
/**
 * @package  Imagelinker Component
 * @version  1.3
 * @license  GNU General Public License version 2
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

class ImagelinkerModel extends FormModel
{

    /**
     * Scans for unlinked images in selected media folders.
     */
    public function scanForUnlinkedImages(array $selectedFolders = [], bool $ignoreCase = false): array|false
    {
      
        $cleanSelectedFolders = [];
        foreach ($selectedFolders as $folder) {
            $cleanSelectedFolders[] = Path::clean($folder);
        }

        if (empty($cleanSelectedFolders)) {
              $app = Factory::getApplication();
              $app->enqueueMessage(Text::_('COM_IMAGELINKER_NO_FOLDERS_SELECTED'), 'warning');
              return false;
          }

          $allMediaImages = $this->getAllMediaImages($cleanSelectedFolders);
          $referencedImages = $this->getReferencedImages();
          
          if ($ignoreCase) {
              $loweredReferenced = array_map('strtolower', $referencedImages);
              $unlinkedImages = array_values(array_filter(
                  $allMediaImages,
                  function ($img) use ($loweredReferenced) {
                      return !in_array(strtolower($img), $loweredReferenced);
                  }
              ));
          } else {
              $unlinkedImages = array_values(array_diff($allMediaImages, $referencedImages));
          }

          return $unlinkedImages;
    }

    /**
     * Deletes a single image from the filesystem.
     */
    public function deleteUnlinkedImage(string $imagePath): bool
    {
        $app = Factory::getApplication();

        if (empty($imagePath)) {
            $app->enqueueMessage(Text::_('COM_IMAGELINKER_NO_CHANGES'), 'warning');
            return false;
        }

        try {
            $fullPath = Path::clean(JPATH_ROOT . '/' . ltrim($imagePath, '/'));
            
            if (!File::exists($fullPath)) {
                $app->enqueueMessage(Text::sprintf('COM_IMAGELINKER_FILE_NOT_FOUND', htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8')), 'warning');
                return false;
            }
            
            // Attempt to delete the file
            if (!File::delete($fullPath)) {
                $app->enqueueMessage(Text::sprintf('COM_IMAGELINKER_DELETE_FAILED', htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8')), 'warning');
                return false;
            }

            return true;
        
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::_('COM_IMAGELINKER_DELETE_ERROR') . ': ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Scans various database tables for image references.
     *
     * @return  array  List of unique image paths relative to JPATH_ROOT.
     */
    protected function getReferencedImages(): array
    {
        $app = Factory::getApplication();
        $referencedImages = [];

        // Create a Joomla database object using the updated version of $this->getDbo 
        try {
            $db = $this->getDatabase();
        } catch (\Exception $e) { 
            $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_ERROR_CONNECT_DATABASE', $e->getMessage()), 'error');
            return [];
        }
        
        // 1. Scan #__content (articles)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['introtext', 'fulltext', 'images']))
                ->from($db->quoteName('#__content'));
            $db->setQuery($query);
            $articles = $db->loadObjectList();

            foreach ($articles as $article) {
                // Scan introtext and fulltext for <img> tags
                $texts = [$article->introtext, $article->fulltext];
                foreach ($texts as $text) {
                    preg_match_all('/<img[^>]+src=["\'](.*?)["\']/i', (string)$text, $matches);
                    foreach ($matches[1] as $src) {
                        $this->addReferencedImage($src, $referencedImages);
                    }
                }

                // Parse images field (JSON)
                $imagesField = json_decode((string)$article->images, true);
                if (is_array($imagesField)) {
                    foreach (['image_intro', 'image_fulltext'] as $field) {
                        if (!empty($imagesField[$field])) {
                            $this->addReferencedImage($imagesField[$field], $referencedImages);
                        }
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_QUERY_FAILED', '#__content', $e->getMessage()), 'error');
        }

        // 2. Scan #__modules (for Custom HTML module content)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['content', 'params']))
                ->from($db->quoteName('#__modules'))
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_custom'));
            $db->setQuery($query);
            $modules = $db->loadObjectList();

            foreach ($modules as $module) {
                // Scan content for <img> tags
                preg_match_all('/<img[^>]+src=["\'](.*?)["\']/i', (string)$module->content, $matches);
                foreach ($matches[1] as $src) {
                    $this->addReferencedImage($src, $referencedImages);
                }

                // Parse params field for module background image
                $params = json_decode((string)$module->params, true);
                if (is_array($params) && !empty($params['backgroundimage'])) {
                    $this->addReferencedImage($params['backgroundimage'], $referencedImages);
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_QUERY_FAILED', '#__modules', $e->getMessage()), 'error');
        }

        // 3. Scan #__categories (image fields)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__categories'));
            $db->setQuery($query);
            $categories = $db->loadObjectList();

            foreach ($categories as $category) {
                $params = json_decode((string)$category->params, true);
                if (is_array($params) && !empty($params['image'])) {
                    $this->addReferencedImage($params['image'], $referencedImages);
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_QUERY_FAILED', '#__categories', $e->getMessage()), 'error');
        }

        // 4. Scan #__contact_details (intro images)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('image'))
                ->from($db->quoteName('#__contact_details'));
            $db->setQuery($query);
            $contacts = $db->loadObjectList();

            foreach ($contacts as $contact) {
                if (!empty($contact->image)) {
                    $this->addReferencedImage($contact->image, $referencedImages);
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_QUERY_FAILED', '#__contact_details', $e->getMessage()), 'error');
        }

        // 5. Scan #__banners (banner images)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__banners'));
            $db->setQuery($query);
            $banners = $db->loadObjectList();

            foreach ($banners as $banner) {
                $params = json_decode((string)$banner->params, true);
                if (is_array($params) && !empty($params['imageurl'])) {
                    $this->addReferencedImage($params['imageurl'], $referencedImages);
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_QUERY_FAILED', '#__banners', $e->getMessage()), 'error');
        }

        // 6. Scan #__newsfeeds (image fields)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('images'))
                ->from($db->quoteName('#__newsfeeds'));
            $db->setQuery($query);
            $newsfeeds = $db->loadObjectList();

            foreach ($newsfeeds as $newsfeed) {
                $imagesField = json_decode((string)$newsfeed->images, true);
                if (is_array($imagesField)) {
                    foreach (['image_first', 'image_second'] as $field) {
                        if (!empty($imagesField[$field])) {
                            $this->addReferencedImage($imagesField[$field], $referencedImages);
                        }
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_QUERY_FAILED', '#__newsfeeds', $e->getMessage()), 'error');
        }

        // 7. Scan #__menu (menu item images)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__menu'));
            $db->setQuery($query);
            $menuItems = $db->loadObjectList();

            foreach ($menuItems as $menuItem) {
                $params = json_decode((string)$menuItem->params, true);
                if (is_array($params)) {
                    foreach ($params as $key => $value) {
                        if (str_ends_with($key, '_image') && !empty($value)) {
                            $this->addReferencedImage($value, $referencedImages);
                        }
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_QUERY_FAILED', '#__menu', $e->getMessage()), 'error');
        }

        // 8. Scan #__tags (tag images)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('images'))
                ->from($db->quoteName('#__tags'));
            $db->setQuery($query);
            $tags = $db->loadObjectList();

            foreach ($tags as $tag) {
                $imagesField = json_decode((string)$tag->images, true);
                if (is_array($imagesField)) {
                    foreach (['image_intro', 'image_fulltext'] as $field) {
                        if (!empty($imagesField[$field])) {
                            $this->addReferencedImage($imagesField[$field], $referencedImages);
                        }
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_QUERY_FAILED', '#__tags', $e->getMessage()), 'error');
        }

        // 9. Scan #__users (for user profile images in params)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__users'));
            $db->setQuery($query);
            $users = $db->loadObjectList();

            foreach ($users as $user) {
                $params = json_decode((string)$user->params, true);
                if (is_array($params) && !empty($params['profile_image'])) {
                    $this->addReferencedImage($params['profile_image'], $referencedImages);
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_QUERY_FAILED', '#__users', $e->getMessage()), 'error');
        }

        // 10. Scan #__template_styles (template logo images)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('params'))
                ->from($db->quoteName('#__template_styles'));
            $db->setQuery($query);
            $templates = $db->loadObjectList();

            foreach ($templates as $template) {
                $params = json_decode((string)$template->params, true);
                if (is_array($params) && !empty($params['logoFile'])) {
                    $this->addReferencedImage($params['logoFile'], $referencedImages);
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_QUERY_FAILED', '#__template_styles', $e->getMessage()), 'error');
        }

        // 11. Scan #__schemaorg (Joomla 5 Schema.org data)
        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('schema'))
                ->from($db->quoteName('#__schemaorg'));
            $db->setQuery($query);
            $schemaItems = $db->loadObjectList();

            foreach ($schemaItems as $item) {
                $schemaData = json_decode((string)$item->schema, true);

                if (is_array($schemaData) && !empty($schemaData['image'])) {
                    $imagePath = $schemaData['image'];
                    $cleanedPath = strtok($imagePath, '#');

                    if ($cleanedPath) {
                        $this->addReferencedImage($cleanedPath, $referencedImages);
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_QUERY_FAILED', '#__schemaorg', $e->getMessage()), 'error');
        }

        return array_unique($referencedImages);
    }

    /**
     * Cleans and adds an image source to the referenced images array.
     */
    protected function addReferencedImage(string $src, array &$referencedImages): void
    {
        $cleanSrc = strtok($src, '#'); //Remove any fragments from the URL, such as #joomlaImage
        $root = Uri::root();
        
        // Handle valid image URLs
        if (filter_var($cleanSrc, FILTER_VALIDATE_URL)) {
           if (!str_starts_with($cleanSrc, $root)) {
              return; // If $cleanSrc is a valid URL but not from this site, reject it
           }

           $cleanSrc = substr($cleanSrc, strlen($root)); // Strip the root to make it a relative path
        }
        
        $cleanSrc = Path::clean('/' . ltrim($cleanSrc, '/'));
        $referencedImages[] = $cleanSrc;
    }

    /**
     * Gets all image files from the specified folders.
     */
    public function getAllMediaImages(array $folders): array
    {
        $allMediaFiles = [];

        foreach ($folders as $folder) {
            $fullPath = Path::clean(JPATH_ROOT . '/' . $folder);
            if (is_dir($fullPath)) {
                foreach (Folder::files($fullPath, '.', false, true) as $fullFilePath) {
                    $fullFilePath = Path::clean($fullFilePath);
                    if (MediaHelper::isImage($fullFilePath)) {
                        $relativeFilePath = Path::clean(substr($fullFilePath, strlen(JPATH_ROOT)));
                        $allMediaFiles[] = $relativeFilePath;
                    }
                }
            }
        }

        return array_unique($allMediaFiles);
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
	
      if (empty($form)) {
          $app = Factory::getApplication();
          $app->enqueueMessage(Text::_('COM_IMAGELINKER_FORM_NOT_LOADED'), 'error'); // Won't show if $form contains no data
          return false;
      }

      return $form;
      }      

    /**
     * Method to get the data that should be injected in the form.
     * Moved the getItem() default logic directly into here where FormModel expects it.
     */
    protected function loadFormData(): mixed
       {
        $app = Factory::getApplication();
        $data = [];

        // Check if the user state has data (e.g. in the case of a failed form submission),
        // and pre-load it for the form
        $userStateData = $app->getUserState('com_imagelinker.edit.imagelinker.data', []);

        if (!empty($userStateData)) {
           $data = $userStateData;
        }

        return $data;
       }
}