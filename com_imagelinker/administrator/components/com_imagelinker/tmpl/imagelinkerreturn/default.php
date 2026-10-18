<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Imagelinker
 * @version     1.5
 * @license     GNU General Public License version 2
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

HTMLHelper::_('behavior.core');

$unlinkedImages = $this->unlinkedImages;
$processedImages = $this->processedImages;

// Option to display the status of all images.
$returnAll = (bool) $this->params->get('return_all', 0);

// Determine which dataset the table should display.
$tableItems = $returnAll ? $processedImages : $unlinkedImages;

$unlinkedCount = count($unlinkedImages);
$displayCount = count($tableItems);
?>

<form action="<?php echo Route::_('index.php?option=com_imagelinker&view=imagelinkerreturn'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3><?php echo Text::_('COM_IMAGELINKER_RESULTS_TITLE'); ?></h3>
                    </div>

                    <div class="card-body">
                        <div class="row mb-3 align-items-center">
                            <div class="btn-toolbar">
                                <button type="submit" class="btn btn-danger mx-3" onclick="if (confirm('<?php echo Text::_('COM_IMAGELINKER_CONFIRM_DELETE_SELECTED'); ?>')) { Joomla.submitform('imagelinker.delete'); }" <?php echo $unlinkedCount > 0 ? '' : 'disabled="disabled"'; ?>>
                                    <span class="icon-trash" aria-hidden="true"></span>
                                    <?php echo Text::_('COM_IMAGELINKER_DELETE_SELECTED_BUTTON'); ?>
                                </button>
                            </div>
                        </div>

                        <?php if ($displayCount > 0) : ?>
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 1%;" class="text-center">
                                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                                        </th>
                                        <th style="width: 15%;">
                                            <?php echo Text::_('COM_IMAGELINKER_IMAGE_PREVIEW'); ?>
                                        </th>
                                        <th>
                                            <?php echo Text::_('COM_IMAGELINKER_IMAGE_PATH'); ?>
                                        </th>
                                        <?php if ($returnAll) : ?>
                                            <th style="width: 35%;">
                                                <?php echo Text::_('COM_IMAGELINKER_IMAGE_STATUS'); ?>
                                            </th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($tableItems as $i => $item) : ?>
                                        <?php
                                        // Prepare the image data according to the selected display mode.
                                        if (!$returnAll) {
                                            $image = $item;
                                            $imagePath = Uri::root() . ltrim($image, '/');
                                            $status = 'unlinked';
                                            $references = [];
                                        } else {
                                            $image = $item['path'];
                                            $status = $item['status'];
                                            $references = $item['references'];
                                            $imagePath = Uri::root() . ltrim($image, '/');
                                        }

                                        // Only allow deletion of unlinked images.
                                        $canDelete = $status === 'unlinked';
                                        ?>

                                        <tr>
                                            <td class="text-center">
                                                <?php if ($canDelete) : ?>
                                                    <?php echo HTMLHelper::_(
                                                        'grid.id',
                                                        $i,
                                                        htmlspecialchars($image, ENT_QUOTES, 'UTF-8')
                                                    ); ?>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if (MediaHelper::isImage($image)) : ?>
                                                    <img src="<?php echo $imagePath; ?>" alt="Preview <?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" style="max-width: 150px; border-radius: 3px;">
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <code><?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?></code>
                                            </td>

                                            <?php if ($returnAll) : ?>
                                                <td>
                                                    <?php if ($status === 'linked') : ?>
                                                        <span class="badge bg-success">
                                                            <?php echo Text::_('COM_IMAGELINKER_IMAGE_STATUS_LINKED'); ?>
                                                        </span>

                                                        <div class="small mt-2">
                                                            <?php foreach ($references as $reference) : ?>
                                                                <div class="mb-2">
                                                                    <strong><?php echo htmlspecialchars($reference['table'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                                    — ID: <?php echo (int) $reference['id']; ?>
                                                                    <br>
                                                                    <code><?php echo htmlspecialchars($reference['field'], ENT_QUOTES, 'UTF-8'); ?></code>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php else : ?>
                                                        <span class="badge bg-danger">
                                                            <?php echo Text::_('COM_IMAGELINKER_IMAGE_STATUS_UNLINKED'); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <div class="alert alert-info">
                                <?php echo Text::_('COM_IMAGELINKER_NO_UNLINKED_IMAGES'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
