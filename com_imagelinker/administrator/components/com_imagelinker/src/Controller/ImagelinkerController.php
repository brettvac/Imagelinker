<?php
/**
 * @package  Imagelinker Component
 * @version  1.3
 * @license  GNU General Public License version 2
 */

namespace Naftee\Component\Imagelinker\Administrator\Controller;

\defined("_JEXEC") or die();

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Router\Route;

class ImagelinkerController extends BaseController
{
    /**
     * Method to scan for unlinked images.
     *
     * @return  void
     *
     * @since   1.0
     */
    public function scan()
    {
        // Check for a valid POST request token to prevent CSRF attacks
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_("JINVALID_TOKEN"), "error");
            $this->setRedirect(Route::_("index.php?option=com_imagelinker&view=imagelinker", false));
            return;
        }

        // Get the model
        $model = $this->getModel("Imagelinker", "Administrator");

        // Get selected folders and ignore case flag from the form submission
        $jform = $this->input->get("jform", [], "array");
               
        $selectedFolders = [];
        if (isset($jform["mediafolders"])) {
          $selectedFolders = (array) $jform["mediafolders"];
        } 

        $ignoreCase = false;
        if (isset($jform["ignore_case"])) {
          $ignoreCase = (bool) $jform["ignore_case"];
        }
       
        // Call the model's scan method with the selected options from the form
        $unlinkedImages = $model->scanForUnlinkedImages($selectedFolders, $ignoreCase);

        // Populate the form data and set user state based on the results
        if (is_array($unlinkedImages)) {
            
            $jform["unlinked_images"] = $unlinkedImages;
            $this->app->setUserState("com_imagelinker.data", (object) $jform);        

            // Display message based on number of unlinked images found
            $unlinkedCount = count($unlinkedImages);
            if ($unlinkedCount > 0) {
                $this->app->enqueueMessage(Text::sprintf("COM_IMAGELINKER_SCAN_SUCCESS", $unlinkedCount), "message");
            } else {
                $this->app->enqueueMessage(Text::_("COM_IMAGELINKER_NO_UNLINKED_IMAGES"), "notice");
            }

            // Redirect to imagelinkerReturn view on success
            $this->setRedirect(Route::_("index.php?option=com_imagelinker&view=imagelinkerReturn", false));
        } else {
            // Redirect to default imagelinker view on error
            $this->setRedirect(Route::_("index.php?option=com_imagelinker&view=imagelinker", false));
        }
    }

    /**
     * Method to delete one or more unlinked images by calling the model.
     *
     * @return  void
     *
     * @since   1.0
     */
    public function delete()
    {
        // Check for a valid POST request token to prevent CSRF attacks
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_("JINVALID_TOKEN"), "error");
            $this->setRedirect(Route::_("index.php?option=com_imagelinker&view=imagelinker", false));
            return;
        }

        // Get the model
        $model = $this->getModel("Imagelinker", "Administrator");

        // Get the cid[] array of image paths
        $cid = $this->input->get("cid", [], "array");

        if (empty($cid)) {
            $this->app->enqueueMessage(Text::_("COM_IMAGELINKER_DELETE_ERROR_NO_PATH"), "error");
            $this->setRedirect(Route::_("index.php?option=com_imagelinker&view=imagelinkerReturn", false));
            return;
        }

        // Initialize deletion counter
        $deletedCount = 0;

        // Iterate over each image path and call model to delete
        foreach ($cid as $imagePath) {
            $result = $model->deleteUnlinkedImage($imagePath);
            if ($result === true) {
               $deletedCount++;
            }
        }
        
        // Display message based on deletion result
        if ($deletedCount > 0) {
            $this->app->enqueueMessage(Text::sprintf("COM_IMAGELINKER_DELETE_SUCCESS", $deletedCount), "message");
        } else {
            $this->app->enqueueMessage(Text::_("COM_IMAGELINKER_NO_CHANGES"), "warning");
        }

        // Update user state to remove processed images
        $currentData = $this->app->getUserState("com_imagelinker.data", (object) []);
        
        $unlinkedImages = [];

        if (isset($currentData->unlinked_images)) {
            $unlinkedImages = (array) $currentData->unlinked_images;
        }
        
        $unlinkedImages = array_values(array_diff($unlinkedImages, $cid));
        $currentData->unlinked_images = $unlinkedImages;
        
        $this->app->setUserState("com_imagelinker.data", $currentData);

        // Redirect to imagelinkerReturn view
        $this->setRedirect(Route::_("index.php?option=com_imagelinker&view=imagelinkerReturn", false));
    }

    /**
     * Cancels the current operation and clears user state.
     *
     * @return  void
     */
    public function cancel()
    {
        $this->app->setUserState("com_imagelinker.data", null);
        $this->setRedirect(Route::_("index.php?option=com_imagelinker&view=imagelinker", false));
    }
}