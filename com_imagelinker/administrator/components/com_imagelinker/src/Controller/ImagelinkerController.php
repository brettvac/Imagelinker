<?php
/**
 * @package  Imagelinker Component
 * @license  GNU General Public License version 2
 */

namespace Naftee\Component\Imagelinker\Administrator\Controller;

\defined("_JEXEC") or die();

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Factory;
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
            $this->setRedirect(
                Route::_(
                    "index.php?option=com_imagelinker&view=imagelinker",
                    false,
                ),
            );
            return;
        }

        // Get the model
        $model = $this->getModel("Imagelinker", "Administrator");

        // Get selected folders and case sensitivity from the form submission
        $jform = $this->input->get("jform", [], "array");
        $selectedFolders = isset($jform["folders"])
            ? (array) $jform["folders"]
            : [];
        $caseSensitive = isset($jform["case_sensitive"])
            ? (bool) $jform["case_sensitive"]
            : false;

        // Call the model's scan method
        $unlinkedImages = $model->scanForUnlinkedImages(
            $selectedFolders,
            $caseSensitive,
        );

        // Handle form data and user state
        if (is_array($unlinkedImages)) {
            $jform["unlinked_images"] = $unlinkedImages;
            $this->app->setUserState("com_imagelinker.data", (object) $jform);

            // Display message based on number of unlinked images found
            $unlinkedCount = count($unlinkedImages);
            if ($unlinkedCount > 0) {
                $this->app->enqueueMessage(
                    Text::sprintf(
                        "COM_IMAGELINKER_SCAN_SUCCESS",
                        $unlinkedCount,
                    ),
                    "message",
                );
            } else {
                $this->app->enqueueMessage(
                    Text::_("COM_IMAGELINKER_SCAN_NO_BROKEN"),
                    "warning",
                );
            }

            // Redirect to imagelinkerReturn view on success
            $this->setRedirect(
                Route::_(
                    "index.php?option=com_imagelinker&view=imagelinkerReturn",
                    false,
                ),
            );
        } else {
            // Redirect to imagelinker view on error
            $this->setRedirect(
                Route::_(
                    "index.php?option=com_imagelinker&view=imagelinker",
                    false,
                ),
            );
        }
    }

    /**
     * Method to delete one or more unlinked images.
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
            $this->setRedirect(
                Route::_(
                    "index.php?option=com_imagelinker&view=imagelinker",
                    false,
                ),
            );
            return;
        }

        // Get the model
        $model = $this->getModel("Imagelinker", "Administrator");

        // Get the cid[] array of image paths
        $cid = $this->input->get("cid", [], "array");

        if (empty($cid)) {
            $this->app->enqueueMessage(
                Text::_("COM_IMAGELINKER_DELETE_ERROR_NO_PATH"),
                "error",
            );
            $this->setRedirect(
                Route::_(
                    "index.php?option=com_imagelinker&view=imagelinkerReturn",
                    false,
                ),
            );
            return;
        }

        // Initialize deletion counter
        $deletedCount = 0;

        // Iterate over each image path and call model to delete
        foreach ($cid as $imagePath) {
            $result = $model->deleteUnlinkedImages($imagePath);
            if ($result === false) {
                $this->app->enqueueMessage(
                    Text::sprintf(
                        "COM_IMAGELINKER_DELETE_ERROR",
                        htmlspecialchars($imagePath, ENT_QUOTES, "UTF-8"),
                    ),
                    "error",
                );
            } elseif ($result > 0) {
                $deletedCount += $result;
            }
        }

        // Update user state to remove processed images
        $currentData = $this->app->getUserState(
            "com_imagelinker.data",
            (object) [],
        );
        $unlinkedImages = isset($currentData->unlinked_images)
            ? (array) $currentData->unlinked_images
            : [];
        $unlinkedImages = array_values(array_diff($unlinkedImages, $cid));
        $currentData->unlinked_images = $unlinkedImages;
        $this->app->setUserState("com_imagelinker.data", $currentData);

        // Display message based on deletion result
        if ($deletedCount > 0) {
            $this->app->enqueueMessage(
                Text::sprintf("COM_IMAGELINKER_DELETE_SUCCESS", $deletedCount),
                "message",
            );
        } else {
            $this->app->enqueueMessage(
                Text::_("COM_IMAGELINKER_DELETE_NO_CHANGES"),
                "warning",
            );
        }

        // Redirect to imagelinkerReturn view
        $this->setRedirect(
            Route::_(
                "index.php?option=com_imagelinker&view=imagelinkerReturn",
                false,
            ),
        );
    }

    /**
     * Cancels the current operation and clears user state.
     *
     * @return  void
     */
    public function cancel()
    {
        $this->app->setUserState("com_imagelinker.data", null);
        $this->setRedirect(Route::_("index.php?option=com_imagelinker", false));
    }
}
