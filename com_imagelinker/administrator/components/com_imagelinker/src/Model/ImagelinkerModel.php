<?php
/**
 * @package  Imagelinker Component
 * @license  GNU General Public License version 2
 */

namespace Naftee\Component\Imagelinker\Administrator\Model;

\defined("_JEXEC") or die();

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Path;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Helper\MediaHelper;

class ImagelinkerModel extends AdminModel
{
    protected $unlinkedImages = [];

    /**
     * Scans various database tables (e.g., #__content, #__modules, #__categories) for image references
     * in content and JSON fields.Returns a list of unique image paths relative to JPATH_ROOT.
     *
     * @param   bool  $caseSensitive  Whether to return paths in a case-sensitive manner for comparison.
     * @return  array  List of unique image paths relative to JPATH_ROOT (e.g., 'images/sample.jpg').
     * @throws  \RuntimeException  If a database query fails.
     */
    protected function getReferencedImages(bool $caseSensitive = false): array
    {
        $db = $this->getDbo();
        $app = Factory::getApplication();

        $referencedImages = [];

        // Helper function to add image to referencedImages
        $addImage = function ($src) use (&$referencedImages, $caseSensitive) {
            $cleanSrc = Path::clean("/" . ltrim($src, "/"));
            if (
                filter_var($cleanSrc, FILTER_VALIDATE_URL) &&
                !str_starts_with($cleanSrc, Uri::root())
            ) {
                return;
            }
            $referencedImages[] = $caseSensitive
                ? $cleanSrc
                : strtolower($cleanSrc);
        };

        // 1. Scan #__content (articles)
        try {
            $query = $db
                ->getQuery(true)
                ->select($db->quoteName(["introtext", "fulltext", "images"]))
                ->from($db->quoteName("#__content"));
            $db->setQuery($query);
            $articles = $db->loadObjectList();

            foreach ($articles as $article) {
                // Scan introtext and fulltext for <img> tags
                $texts = [$article->introtext, $article->fulltext];
                foreach ($texts as $text) {
                    preg_match_all(
                        '/<img[^>]+src=["\'](.*?)["\']/i',
                        (string) $text,
                        $matches,
                    );
                    foreach ($matches[1] as $src) {
                        // Strip #joomlaImage:// and anything after it
                        $cleanSrc = strtok($src, "#");
                        $addImage($cleanSrc);
                    }
                }

                // Parse images field (JSON)
                $imagesField = json_decode((string) $article->images, true);
                if (is_array($imagesField)) {
                    foreach (["image_intro", "image_fulltext"] as $field) {
                        if (!empty($imagesField[$field])) {
                            // Strip #joomlaImage:// and anything after it
                            $cleanImage = strtok($imagesField[$field], "#");
                            $addImage($cleanImage);
                        }
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(
                Text::_("COM_IMAGELINKER_DB_ERROR") . $e->getMessage(),
                "error",
            );
        }

        // 2. Scan #__modules (for Custom HTML module content)
        try {
            $query = $db
                ->getQuery(true)
                ->select($db->quoteName(["content", "params"]))
                ->from($db->quoteName("#__modules"))
                ->where(
                    $db->quoteName("module") . " = " . $db->quote("mod_custom"),
                );
            $db->setQuery($query);
            $modules = $db->loadObjectList();

            foreach ($modules as $module) {
                // Scan content for <img> tags
                preg_match_all(
                    '/<img[^>]+src=["\'](.*?)["\']/i',
                    (string) $module->content,
                    $matches,
                );
                foreach ($matches[1] as $src) {
                    // Strip #joomlaImage:// and anything after it
                    $cleanSrc = strtok($src, "#");
                    $addImage($cleanSrc);
                }

                // Parse params field for background image
                $params = json_decode((string) $module->params, true);
                if (is_array($params) && !empty($params["backgroundimage"])) {
                    // Strip #joomlaImage:// and anything after it
                    $bgImage = strtok($params["backgroundimage"], "#");
                    if ($bgImage) {
                        $addImage($bgImage);
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(
                Text::sprintf(
                    "COM_IMAGELINKER_ERROR_QUERY_FAILED",
                    "modules",
                    $e->getMessage(),
                ),
                "error",
            );
        }

        // 3. Scan #__categories (image fields)
        try {
            $query = $db
                ->getQuery(true)
                ->select($db->quoteName("params"))
                ->from($db->quoteName("#__categories"));
            $db->setQuery($query);
            $categories = $db->loadObjectList();

            foreach ($categories as $category) {
                $params = json_decode((string) $category->params, true);
                if (is_array($params)) {
                    foreach (["image", "image_alt"] as $field) {
                        if (!empty($params[$field])) {
                            $addImage($params[$field]);
                        }
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(
                Text::_("COM_IMAGELINKER_DB_ERROR") . $e->getMessage(),
                "error",
            );
        }

        // 4. Scan #__contact_details (intro images)
        try {
            $query = $db
                ->getQuery(true)
                ->select($db->quoteName("image"))
                ->from($db->quoteName("#__contact_details"));
            $db->setQuery($query);
            $contacts = $db->loadObjectList();

            foreach ($contacts as $contact) {
                if (!empty($contact->image)) {
                    $addImage($contact->image);
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(
                Text::_("COM_IMAGELINKER_DB_ERROR") . $e->getMessage(),
                "error",
            );
        }

        // 5. Scan #__banners (banner images)
        try {
            $query = $db
                ->getQuery(true)
                ->select($db->quoteName("params"))
                ->from($db->quoteName("#__banners"));
            $db->setQuery($query);
            $banners = $db->loadObjectList();

            foreach ($banners as $banner) {
                $params = json_decode((string) $banner->params, true);

                if (is_array($params)) {
                    if (!empty($params["imageurl"])) {
                        $addImage($params["imageurl"]);
                    }
                    if (!empty($params["alt_image"])) {
                        $addImage($params["alt_image"]);
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(
                Text::_("COM_IMAGELINKER_DB_ERROR") . $e->getMessage(),
                "error",
            );
        }

        // 6. Scan #__newsfeeds (image fields)
        try {
            $query = $db
                ->getQuery(true)
                ->select($db->quoteName("images"))
                ->from($db->quoteName("#__newsfeeds"));
            $db->setQuery($query);
            $newsfeeds = $db->loadObjectList();

            foreach ($newsfeeds as $newsfeed) {
                $imagesField = json_decode((string) $newsfeed->images, true);
                if (is_array($imagesField)) {
                    foreach (["image_first", "image_second"] as $field) {
                        if (!empty($imagesField[$field])) {
                            $addImage($imagesField[$field]);
                        }
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(
                Text::_("COM_IMAGELINKER_DB_ERROR") . $e->getMessage(),
                "error",
            );
        }

        // 7. Scan #__menu (menu item images)
        try {
            $query = $db
                ->getQuery(true)
                ->select($db->quoteName("params"))
                ->from($db->quoteName("#__menu"));
            $db->setQuery($query);
            $menuItems = $db->loadObjectList();

            foreach ($menuItems as $menuItem) {
                $params = json_decode((string) $menuItem->params, true);
                if (is_array($params) && !empty($params["menu_image"])) {
                    $addImage($params["menu_image"]);
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(
                Text::_("COM_IMAGELINKER_DB_ERROR") . $e->getMessage(),
                "error",
            );
        }

        // 8. Scan #__fields_values (custom fields of type media)
        try {
            $query = $db
                ->getQuery(true)
                ->select($db->quoteName("fv.value"))
                ->from($db->quoteName("#__fields_values", "fv"))
                ->join(
                    "INNER",
                    $db->quoteName("#__fields", "f") .
                        " ON " .
                        $db->quoteName("f.id") .
                        " = " .
                        $db->quoteName("fv.field_id"),
                )
                ->where($db->quoteName("f.type") . " = " . $db->quote("media"));
            $db->setQuery($query);
            $fieldValues = $db->loadObjectList();

            foreach ($fieldValues as $fieldValue) {
                if (!empty($fieldValue->value)) {
                    $addImage($fieldValue->value);
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(
                Text::_("COM_IMAGELINKER_DB_ERROR") . $e->getMessage(),
                "error",
            );
        }

        // 9. Scan #__tags (tag images)
        try {
            $query = $db
                ->getQuery(true)
                ->select($db->quoteName("images"))
                ->from($db->quoteName("#__tags"));
            $db->setQuery($query);
            $tags = $db->loadObjectList();

            foreach ($tags as $tag) {
                $imagesField = json_decode((string) $tag->images, true);
                if (is_array($imagesField)) {
                    foreach (["image_intro", "image_fulltext"] as $field) {
                        if (!empty($imagesField[$field])) {
                            $addImage($imagesField[$field]);
                        }
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(
                Text::_("COM_IMAGELINKER_DB_ERROR") . $e->getMessage(),
                "error",
            );
        }

        // 10. Scan #__users (user profile images)
        try {
            $query = $db
                ->getQuery(true)
                ->select(
                    $db->quoteName(["user_id", "profile_key", "profile_value"]),
                )
                ->from($db->quoteName("#__user_profiles"))
                ->where(
                    $db->quoteName("profile_key") .
                        " LIKE " .
                        $db->quote("profile.%"),
                );

            $db->setQuery($query);
            $rows = $db->loadAssocList();

            $profiles = [];
            foreach ($rows as $row) {
                $key = str_replace("profile.", "", $row["profile_key"]);
                $value = json_decode($row["profile_value"], true);
                $profiles[$row["user_id"]][$key] =
                    $value === null ? $row["profile_value"] : $value;
            }

            foreach ($profiles as $userId => $profile) {
                if (!empty($profile["picture"])) {
                    $addImage($profile["picture"]);
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(
                Text::_("COM_IMAGELINKER_DB_ERROR") . $e->getMessage(),
                "error",
            );
        }

        // 11. Scan #__template_styles (template logo images)
        try {
            $query = $db
                ->getQuery(true)
                ->select($db->quoteName("params"))
                ->from($db->quoteName("#__template_styles"));
            $db->setQuery($query);
            $templates = $db->loadObjectList();

            foreach ($templates as $template) {
                $params = json_decode((string) $template->params, true);
                if (is_array($params) && !empty($params["logoFile"])) {
                    // Strip #joomlaImage:// and anything after it
                    $logoImage = strtok($params["logoFile"], "#");
                    if ($logoImage) {
                        $addImage($logoImage);
                    }
                }
            }
        } catch (\RuntimeException $e) {
            $app->enqueueMessage(
                Text::sprintf(
                    "COM_IMAGELINKER_ERROR_QUERY_FAILED",
                    "template styles",
                    $e->getMessage(),
                ),
                "error",
            );
        }

        // Remove duplicates and return
        return array_unique($referencedImages);
    }

    /**
     * Scans for unlinked images in selected media folders.
     *
     * @param   array   $selectedFolders  Folders to scan relative to JPATH_ROOT.
     * @param   bool    $caseSensitive    Whether to perform case-sensitive matching.
     * @return array|false                Array of unlinked image paths, or false on failure.
     */
    public function scanForUnlinkedImages(
        array $selectedFolders = [],
        bool $caseSensitive = false,
    ): array|false {
        $app = Factory::getApplication();
        $this->unlinkedImages = [];

        try {
            if (empty($selectedFolders)) {
                $config = Factory::getConfig();
                $mediaDir = $config->get("media_directory", "images");
                $foldersToScan = [Path::clean(JPATH_ROOT . "/" . $mediaDir)];
            } else {
                $cleanSelectedFolders = [];
                foreach ($selectedFolders as $folder) {
                    $cleanSelectedFolders[] = Path::clean($folder);
                }
                $foldersToScan = $cleanSelectedFolders;
            }

            if (empty($foldersToScan)) {
                $app->enqueueMessage(
                    Text::_("COM_IMAGELINKER_NO_FOLDERS_SELECTED"),
                    "warning",
                );
                return false;
            }

            $allMediaImages = $this->getAllMediaImages(
                $foldersToScan,
                $caseSensitive,
            );
            $referencedImages = $this->getReferencedImages($caseSensitive);

            $this->unlinkedImages = array_values(
                array_diff($allMediaImages, $referencedImages),
            );

            return $this->unlinkedImages;
        } catch (\Exception $e) {
            $app->enqueueMessage(
                Text::_("COM_IMAGELINKER_DB_ERROR") . $e->getMessage(),
                "error",
            );
            return false;
        }
    }

    /**
     * Deletes a single unlinked image from the filesystem.
     *
     * @param   string  $imagePath  Full path to the image relative to JPATH_ROOT to delete.
     * @return  int|false  Number of images deleted (0 or 1) on success, false on critical failure.
     *
     * @since   1.0
     */
    public function deleteUnlinkedImages(string $imagePath)
    {
        $app = Factory::getApplication();

        // Validate image path
        if (empty($imagePath)) {
            $app->enqueueMessage(
                Text::_("COM_IMAGELINKER_FIX_NO_CHANGES"),
                "warning",
            );
            return 0;
        }

        try {
            // Clean and normalize the path
            $fullPath = Path::clean(JPATH_ROOT . "/" . ltrim($imagePath, "/"));

            if (!File::exists($fullPath)) {
                $app->enqueueMessage(
                    Text::sprintf(
                        "COM_IMAGELINKER_FILE_NOT_FOUND",
                        htmlspecialchars($imagePath, ENT_QUOTES, "UTF-8"),
                    ),
                    "warning",
                );
                return 0;
            }

            if (!File::delete($fullPath)) {
                $app->enqueueMessage(
                    Text::sprintf(
                        "COM_IMAGELINKER_DELETE_FAILED_FILE",
                        htmlspecialchars($imagePath, ENT_QUOTES, "UTF-8"),
                    ),
                    "warning",
                );
                return 0;
            }

            return 1;
        } catch (\Exception $e) {
            $app->enqueueMessage(
                Text::_("COM_IMAGELINKER_DELETE_ERROR") .
                    ": " .
                    $e->getMessage(),
                "error",
            );
            return false;
        }
    }

    /**
     * Fetches a list of subdirectories within the main media 'images' folder.
     *
     * @param   string  $baseMediaRoot  Optional. The base path for media files, defaults to 'images'.
     * @return  array   An array of folder paths relative to JPATH_ROOT.
     */
    public function getMediaFolders($baseMediaRoot = "")
    {
        $folders = [];
        $imagePath = ComponentHelper::getParams("com_media")->get(
            "image_path",
            "images",
        );

        $baseFolder = !empty($baseMediaRoot) ? $baseMediaRoot : $imagePath;
        $fullPath = Path::clean(JPATH_ROOT . "/" . $baseFolder);

        if (is_dir($fullPath)) {
            $subfolders = Folder::folders($fullPath);
            foreach ($subfolders as $folder) {
                $folders[] = Path::clean($baseFolder . "/" . $folder);
            }
        }
        $folders[] = Path::clean($baseFolder); // Add the base folder itself if it exists

        sort($folders);
        return $folders;
    }

    /**
     * Gets all image files from the specified folders.
     *
     * @param   array   $folders        Array of folder paths relative to JPATH_ROOT.
     * @param   bool    $caseSensitive  Whether to return paths in a case-sensitive manner for comparison.
     * @return  array   List of full image paths relative to JPATH_ROOT (e.g., '/images/stories/myimage.jpg').
     */
    public function getAllMediaImages(
        array $folders,
        bool $caseSensitive = false,
    ): array {
        $allMediaFiles = [];

        foreach ($folders as $folder) {
            $fullPath = Path::clean(JPATH_ROOT . "/" . $folder);

            if (is_dir($fullPath)) {
                // $file is already a full absolute path
                foreach (
                    Folder::files($fullPath, ".", false, true) // Don't scan subdirectories; return full absolute path
                    as $fullFilePath
                ) {
                    $fullFilePath = Path::clean($fullFilePath);

                    if (MediaHelper::isImage($fullFilePath)) {
                        $relativeFilePath = Path::clean(
                            str_replace(JPATH_ROOT, "", $fullFilePath),
                        );
                        $allMediaFiles[] = $caseSensitive
                            ? $relativeFilePath
                            : strtolower($relativeFilePath);
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
     * @return  JTable  A JTable object
     *
     * @since   1.6
     */
    public function getTable(
        $type = "Content",
        $prefix = "Joomla\\CMS\\Table\\",
        $options = [],
    ) {
        return Table::getInstance($type, $prefix, $options);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data.
     *
     * @return  \Joomla\CMS\Form\Form|boolean  A Form object on success, false on failure.
     * @since   1.6
     */
    public function getForm($data = [], $loadData = true)
    {
        $app = Factory::getApplication();
        $formName = "imagelinker";

        $form = $this->loadForm("com_imagelinker." . $formName, $formName, [
            "control" => "jform",
            "load_data" => $loadData,
        ]);

        if (empty($form)) {
            $app->enqueueMessage(
                Text::_("COM_IMAGELINKER_FORM_NOT_LOADED"),
                "error",
            );
            return false;
        }

        // Dynamically populate folders field options
        $mediaFolders = $this->getMediaFolders();

        if (!empty($mediaFolders)) {
            $options = [];
            foreach ($mediaFolders as $folder) {
                $options[] = (object) [
                    "value" => $folder,
                    "text" => $folder,
                ];
            }
            $form->setFieldAttribute(
                "folders",
                "options",
                json_encode($options),
            );
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     * @since   1.6
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState(
            "com_imagelinker.data",
            [],
        );

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Method to get a single record (or default data for a new record/state).
     *
     * @param   int  $pk  The id of the record to retrieve. Not applicable for this utility.
     *
     * @return  \stdClass  An object with data to bind to the form and display in the view.
     * @since   1.6
     */
    public function getItem($pk = null)
    {
        $data = Factory::getApplication()->getUserState(
            "com_imagelinker.data",
            new \stdClass(),
        );

        if (!isset($data->folders)) {
            $data->folders = $this->getMediaFolders(); // Pre-select all folders
        }

        $form = $this->getForm(null, false);
        if ($form) {
            foreach ($form->getFieldset("imagelinker") as $field) {
                $fieldName = $field->fieldname; // This is the public property for the field's name

                if (!isset($data->{$fieldName}) && isset($field->default)) {
                    $data->{$fieldName} = $field->default;
                }
            }
        }

        $data->unlinked_images = $this->getState()->get("unlinked_images", []);

        return $data;
    }
}
