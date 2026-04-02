<?php
/**
 * @package  Imagelinker Component
 * @version  1.3
 * @license  GNU General Public License version 2
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Helper\MediaHelper;

//For debugging
use Joomla\CMS\Factory;

HTMLHelper::_('behavior.core');

$unlinkedImages = $this->unlinkedImages ?? [];
$listCount      = count($unlinkedImages);

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
                                <button type="submit" class="btn btn-danger mx-3"
                                    onclick="if (confirm('<?php echo Text::_('COM_IMAGELINKER_CONFIRM_DELETE_SELECTED'); ?>')) { Joomla.submitform('imagelinker.delete'); }"
                                    <?php echo $listCount > 0 ? '' : 'disabled="disabled"'; ?>
                                >
                                    <span class="icon-trash" aria-hidden="true"></span>
                                    <?php echo Text::_('COM_IMAGELINKER_DELETE_SELECTED_BUTTON'); ?>
                                </button>
                            </div>
                        </div>

                        <?php if ($listCount > 0) : ?>
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 1%;" class="text-center">
                                            <?php echo HTMLHelper::_('grid.checkall'); ?>
                                        </th>
                                        <th style="width: 15%;"><?php echo Text::_('COM_IMAGELINKER_UNLINKED_IMAGE_PREVIEW'); ?></th>
                                        <th><?php echo Text::_('COM_IMAGELINKER_UNLINKED_IMAGE_PATH'); ?></th>
                                    </tr>
                                </thead>
                                
                                <tbody>
                                    <?php foreach ($unlinkedImages as $i => $image) : ?>
                                        <?php $imagePath = Uri::root() . ltrim($image, '/'); ?>
                                        <tr>
                                            <td class="text-center">
                                                <?php echo HTMLHelper::_('grid.id', $i, htmlspecialchars($image, ENT_QUOTES, 'UTF-8')); ?>
                                            </td>
                                            <td>
                                                <?php if (MediaHelper::isImage($image)) : ?>
                                                    <img src="<?php echo $imagePath; ?>" alt="Preview <?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" style="max-width: 150px; border-radius: 3px;" />
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?></code>
                                            </td>
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