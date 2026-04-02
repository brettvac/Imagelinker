<?php
/**
 * @package  Imagelinker Component
 * @version  1.3
 * @license  GNU General Public License version 2
 */

namespace Naftee\Component\Imagelinker\Administrator\Field;

use Joomla\CMS\Form\Field\CheckboxesField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\CMS\Language\Text;

\defined('_JEXEC') or die;

class MediafoldersField extends CheckboxesField
{
    protected $type = 'Mediafolders';

    /**
     * Method to get the field options.
     * @return array The field option objects.
     */
    protected function getOptions()
    {
        $options = [];

        // Fetch the component parameters to get the base media directory
        $params = ComponentHelper::getParams('com_imagelinker');
        $mediaDir = $params->get('media_directory', 'images');
        
        $basePath = Path::clean($mediaDir);
        $fullPath = Path::clean(JPATH_ROOT . '/' . $basePath);

        $mediaFolders = [];

        // Scan for folders if the directory exists
        if (is_dir($fullPath)) {
            $mediaFolders[] = $basePath; // Add the root 'images' folder
            
            $subfolders = Folder::folders($fullPath, '.', true, true);
                  
            foreach ($subfolders as $subfolder) {
                // Get the relative path from the Joomla root
                $mediaFolders[] = Path::clean(substr($subfolder, strlen(JPATH_ROOT) + 1)); 
            }
        }  
    
        // Populate folders field options array
        foreach ($mediaFolders as $folder) 
            {
                // Use HTMLHelper to create the standard Joomla option objects
                $options[] = HTMLHelper::_('select.option', $folder, $folder);
            }

        // Merge with any hardcoded options in the XML form and return
        return array_merge(parent::getOptions(), $options);
    }
    
    public function getInput() 
    {
      $options = $this->getOptions();

      // Show the error message if no folders found
      if (empty($options)) {
        $message = Text::_('COM_IMAGELINKER_NO_FOLDERS_AVAILABLE');

        return '<div class="alert alert-warning" role="alert">' . $message . '</div>';
      }

      // Otherwise render normal checkboxes
      return parent::getInput();
    }
}