<?php
/**
 * @package  Imagelinker Component
 * @version  1.4
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

        // Get selected folders from the form submission
        $jform = $this->input->get("jform", [], "array");

        $selectedFolders = [];

        if (isset($jform["mediafolders"])) {
            $selectedFolders = (array) $jform["mediafolders"];
        }

        // Stop if no folders were selected
        if (empty($selectedFolders)) {

            $this->app->enqueueMessage(
                Text::_("COM_IMAGELINKER_NO_FOLDERS_SELECTED"),
                "warning"
            );

            $this->setRedirect(Route::_("index.php?option=com_imagelinker&view=imagelinker", false));;

            return;
        }

        // Get the model
        $model = $this->getModel(
            "Imagelinker",
            "Administrator"
        );

        // Scan for unlinked images and obtain status for all images in the media folder
        $scanResults = $model->scanForUnlinkedImages($selectedFolders);

        $unlinkedImages = $scanResults['unlinked_images'];
        $processedImages = $scanResults['processed_images'];

        // Display any model errors/warnings
        foreach ($model->getErrors() as $error) {

            $this->app->enqueueMessage(
                $error instanceof \Exception
                    ? $error->getMessage()
                    : $error,
                "error"
            );
        }

        // Store results in user state
        $jform["unlinked_images"] = $unlinkedImages;
        $jform['processed_images'] = $processedImages;

        $this->app->setUserState(
            "com_imagelinker.data",
            (object) $jform
        );

        // Display message based on results
        $unlinkedCount = count($unlinkedImages);

        if ($unlinkedCount > 0) {

            $this->app->enqueueMessage(
                Text::sprintf(
                    "COM_IMAGELINKER_SCAN_SUCCESS",
                    $unlinkedCount
                ),
                "message"
            );

        } else {

            $this->app->enqueueMessage(
                Text::_("COM_IMAGELINKER_NO_UNLINKED_IMAGES"),
                "notice"
            );
        }

        // Redirect to results view
        $this->setRedirect(
            Route::_(
                "index.php?option=com_imagelinker&view=imagelinkerReturn",
                false
            )
        );
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

        // Get the cid[] array of image paths
        $cid = $this->input->get("cid", [], "array");

        if (empty($cid)) {
            $this->app->enqueueMessage(Text::_("COM_IMAGELINKER_DELETE_ERROR_NO_PATH"), "error");
            $this->setRedirect(Route::_("index.php?option=com_imagelinker&view=imagelinkerReturn", false));
            return;
        }

        // Initialize deletion counter
        $deletedCount = 0;
        
        // Get the model
        $model = $this->getModel("Imagelinker", "Administrator");

        // Iterate over each image path and call model to delete
        foreach ($cid as $imagePath) {
            
            if (empty($imagePath)) {
                $this->app->enqueueMessage(Text::_('COM_IMAGELINKER_DELETE_ERROR_NO_PATH'), 'error');
                continue; // Skip invalid entries
            }
            
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
        
        // Fetch and display model errors
        $errors = $model->getErrors();
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->app->enqueueMessage($error instanceof \Exception ? $error->getMessage() : $error, 'error');
            }
        }

        // Update user state to remove deleted images
        $currentData = $this->app->getUserState("com_imagelinker.data", (object) []);

        // Update unlinked_images
        $unlinkedImages = [];

        if (isset($currentData->unlinked_images)) {
            $unlinkedImages = (array) $currentData->unlinked_images;
        }

        $currentData->unlinked_images = array_values(
            array_diff($unlinkedImages, $cid)
        );

        // Update processed_images
        $processedImages = [];

        if (isset($currentData->processed_images)) {
            $processedImages = (array) $currentData->processed_images;
        }

        $currentData->processed_images = array_values(
            array_filter(
                $processedImages,
                function ($image) use ($cid) {

                    // Skip malformed entries safely
                    if (!is_array($image) || !isset($image['path'])) {
                        return true;
                    }

                    return !in_array($image['path'], $cid, true);
                }
            )
        );      
        
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