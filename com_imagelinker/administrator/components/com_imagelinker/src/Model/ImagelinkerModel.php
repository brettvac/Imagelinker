<?php
/**
 * @package  Imagelinker Component
 * @version  1.1
 * @license  GNU General Public License version 2
 */

namespace Naftee\Component\Imagelinker\Administrator\Model;

\defined('_JEXEC') or die();

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Helper\MediaHelper;

class ImagelinkerModel extends ListModel
{
    /** @var array Stores unlinked images during processing */
    protected $unlinkedImages = [];

    /**
     * Scans various database tables (e.g., #__content, #__modules, #__categories) for image references
     * in content and JSON fields. Returns a list of unique image paths relative to JPATH_ROOT.
     *
     * @return  array  List of unique image paths relative to JPATH_ROOT (e.g. 'images/sample.jpg').
     */
    protected function getReferencedImages(): array
    {
        $app = Factory::getApplication();
        $referencedImages = [];

        //Database Connection 
        try 
          {
          $db = $this->getDbo();
          } 
          catch (\Exception $e) 
          { 
          $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_ERROR_CONNECT_DATABASE', $e->getMessage()), 'error');
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
                    // TODO: Replace with actual parameter if not 'profile_image'
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
                ->select($db->quoteName('schema')) // The column holding the JSON schema
                ->from($db->quoteName('#__schemaorg'));
            $db->setQuery($query);
            $schemaItems = $db->loadObjectList();

            foreach ($schemaItems as $item) {
                $schemaData = json_decode((string)$item->schema, true);

                // Check if JSON is valid and has an 'image' key
                if (is_array($schemaData) && !empty($schemaData['image'])) {
                    $imagePath = $schemaData['image'];

                    // Clean the path: remove the #joomlaImage... suffix
                    $cleanedPath = strtok($imagePath, '#');

                    if ($cleanedPath) {
                        $this->addReferencedImage($cleanedPath, $referencedImages);
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(Text::sprintf('JLIB_DATABASE_QUERY_FAILED', '#__schemaorg', $e->getMessage()), 'error');
        }

        // Remove duplicates and return
        return array_unique($referencedImages);
    }

    /**
     * Cleans and adds an image source to the referenced images array.
     *
     * @param   string  $src               The raw image source.
     * @param   array   &$referencedImages The array of referenced images to add to.
     *
     * @return  void
     */
    protected function addReferencedImage(string $src, array &$referencedImages): void
    {
        // Strip #joomlaImage:// suffix
        $cleanSrc = strtok($src, '#');

        // Strip the site's root from absolute local URLs
        $root = Uri::root();
        if (filter_var($cleanSrc, FILTER_VALIDATE_URL) && str_starts_with($cleanSrc, $root)) {
            $cleanSrc = substr($cleanSrc, strlen($root));
        }

        // Do not add external images
        if (filter_var($cleanSrc, FILTER_VALIDATE_URL) && !str_starts_with($cleanSrc, $root)) {
            return;
        }

        // Normalize images by prepending /
        $cleanSrc = Path::clean('/' . ltrim($cleanSrc, '/'));
        $referencedImages[] = $cleanSrc;
    }

    /**
     * Scans for unlinked images in selected media folders. Returns the list of unlinked images.
     *
     * @param   array   $selectedFolders  Folders to scan relative to JPATH_ROOT.
     * @param   bool    $ignoreCase       Whether to ignore case in comparisons (default: false).
     *
     * @return  array|false  Array of unlinked image paths, or false on failure.
     */
    public function scanForUnlinkedImages(array $selectedFolders = [], bool $ignoreCase = false): array|false
    {
        $app = Factory::getApplication();

        
          // Always clean the selected folders
          $cleanSelectedFolders = [];
          foreach ($selectedFolders as $folder) {
              $cleanSelectedFolders[] = Path::clean($folder);
          }

          $foldersToScan = $cleanSelectedFolders;

          if (empty($foldersToScan)) {
                $app->enqueueMessage(Text::_('COM_IMAGELINKER_NO_FOLDERS_SELECTED'), 'warning');
                return false;
            }

            $allMediaImages = $this->getAllMediaImages($foldersToScan);

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
     * Deletes a single unlinked image from the filesystem.
     *
     * @param   string  $imagePath  Full path to the image relative to JPATH_ROOT to delete.
     *
     * @return  int|false  Number of images deleted (0 or 1) on success, false on critical failure.
     *
     * @since   1.0
     */
    public function deleteUnlinkedImages(string $imagePath): int|false
    {
        $app = Factory::getApplication();

        // Validate image path
        if (empty($imagePath)) {
            $app->enqueueMessage(Text::_('COM_IMAGELINKER_NO_CHANGES'), 'warning');
            return 0;
        }

        try {
            // Clean and normalize the path
            $fullPath = Path::clean(JPATH_ROOT . '/' . ltrim($imagePath, '/'));
            if (!File::exists($fullPath)) {
                $app->enqueueMessage(Text::sprintf('COM_IMAGELINKER_FILE_NOT_FOUND', htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8')), 'warning');
                return 0;
            }

            if (!File::delete($fullPath)) {
                $app->enqueueMessage(Text::sprintf('COM_IMAGELINKER_DELETE_FAILED', htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8')), 'warning');
                return 0;
            }

            return 1;
        } catch (\Exception $e) {
            $app->enqueueMessage(Text::_('COM_IMAGELINKER_DELETE_ERROR') . ': ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Fetches a list of subdirectories within the main media 'images' folder.
     *
     * @return  array   An array of folder paths relative to JPATH_ROOT.
     */
    public function getMediaFolders(): array
    {
        $folders = []; 
        $params = ComponentHelper::getParams('com_imagelinker');
        $mediaDir = $params->get('media_directory', 'images');  //Get the media directory set in the component configuration
        
        $basePath = Path::clean($mediaDir);

        $fullPath = Path::clean(JPATH_ROOT . '/' . $basePath);

        if (is_dir($fullPath)) {
          $folders = [$basePath];
          $subfolders = Folder::folders($fullPath, '.', true, true);  //Recursively search subfolders and return full paths
                   
          foreach ($subfolders as $subfolder) {
            $relativePath = Path::clean(substr($subfolder, strlen(JPATH_ROOT) + 1)); 
            $folders[] = $relativePath;  //Add image folders to the array
          }
        }      
         
        return $folders;
    }

    /**
     * Gets all image files from the specified folders.
     *
     * @param   array   $folders  Array of folder paths relative to JPATH_ROOT.
     *
     * @return  array   List of full image paths relative to JPATH_ROOT.
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
                        $relativeFilePath = Path::clean(substr($fullFilePath, strlen(JPATH_ROOT) + 1));
                        $allMediaFiles[] = $relativeFilePath;
                    }
                }
            }
        }

        return array_unique($allMediaFiles);
    }

    /**
     * Method to get the table object.
     *
     * @param   string  $type    The table name.
     * @param   string  $prefix  The class prefix.
     * @param   array   $options An optional array of options for the table.
     *
     * @return  \Joomla\CMS\Table\Table  A Table object
     *
     * @since   1.6
     */
    public function getTable($type = 'Content', $prefix = 'Joomla\\CMS\\Table\\', $options = []): \Joomla\CMS\Table\Table
    {
        return Table::getInstance($type, $prefix, $options);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data.
     *
     * @return  \Joomla\CMS\Form\Form|false  A Form object on success, false on failure.
     *
     * @since   1.6
     */
    public function getForm($data = [], $loadData = true): \Joomla\CMS\Form\Form|false
    {
        $app = Factory::getApplication();
        $formName = 'imagelinker';
        $form = $this->loadForm('com_imagelinker.' . $formName, $formName, [
            'control' => 'jform',
            'load_data' => $loadData
        ]);

        if (empty($form)) {
            $app->enqueueMessage(Text::_('COM_IMAGELINKER_FORM_NOT_LOADED'), 'error');
            return false;
        }

        // Dynamically populate folders field options
        $mediaFolders = $this->getMediaFolders();
        if (!empty($mediaFolders)) {
            $options = [];
            foreach ($mediaFolders as $folder) {
                $options[] = (object)['value' => $folder, 'text' => $folder];
            }
            $form->setFieldAttribute('folders', 'options', json_encode($options));
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   1.6
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_imagelinker.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Method to get a single record (or default data for a new record/state).
     *
     * @param   int|null  $pk  The id of the record to retrieve. Not applicable for this utility.
     *
     * @return  \stdClass  An object with data to bind to the form and display in the view.
     *
     * @since   1.6
     */
    public function getItem($pk = null): \stdClass
    {
        $data = Factory::getApplication()->getUserState('com_imagelinker.data', new \stdClass());

        if (!isset($data->folders)) {
            $data->folders = $this->getMediaFolders(); // Pre-select all folders
        }

        $form = $this->getForm(null, false);
        if ($form) {
            foreach ($form->getFieldset('imagelinker') as $field) {
                $fieldName = $field->fieldname;
                if (!isset($data->{$fieldName}) && isset($field->default)) {
                    $data->{$fieldName} = $field->default;
                }
            }
        }

        $data->unlinked_images = $this->getState()->get('unlinked_images', []);

        return $data;
    }
}