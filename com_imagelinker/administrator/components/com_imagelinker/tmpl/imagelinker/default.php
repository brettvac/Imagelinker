<?php
/**
 * @package  Imagelinker Component
 * @version  1.1
 * @license  GNU General Public License version 2
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.core');
HTMLHelper::_('behavior.formvalidator');
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3><?php echo Text::_('COM_IMAGELINKER_TITLE'); ?></h3>
                </div>

                <div class="card-body">
                    <?php if ($this->form): ?>
                        <form action="<?php echo Route::_('index.php?option=com_imagelinker&task=imagelinker.scan'); ?>"
                              method="post"
                              name="adminForm"
                              id="adminForm"
                              class="form-validate">

                            <div class="row">
                                <div class="col-md-6">

                                    <h4><?php echo Text::_('COM_IMAGELINKER_FOLDERS_TO_SCAN'); ?></h4>
                                    <p class="text-muted"><?php echo Text::_('COM_IMAGELINKER_FOLDERS_TO_SCAN_DESC'); ?></p>

                                    <div class="form-check mb-3">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               id="toggle-all-folders"
                                               checked
                                               onclick="document.querySelectorAll('input[name=\'jform[folders][]\']')
                                                        .forEach(cb => cb.checked = this.checked);">
                                        <label class="form-check-label" for="toggle-all-folders">
                                            <?php echo Text::_('COM_IMAGELINKER_TOGGLE_ALL_FOLDERS'); ?>
                                        </label>
                                    </div>

                                    <div class="form-group">
                                        <div class="controls">
                                            <?php if (!empty($this->mediaFolders)): ?>
                                                <?php foreach ($this->mediaFolders as $folder): ?>
                                                    <?php
                                                    $folderId = 'folder_' . preg_replace('/[^a-zA-Z0-9_]/', '', $folder);
                                                    ?>
                                                    <div class="form-check">
                                                        <input type="checkbox"
                                                               class="form-check-input"
                                                               name="jform[folders][]"
                                                               id="<?php echo htmlspecialchars($folderId); ?>"
                                                               value="<?php echo htmlspecialchars($folder); ?>"
                                                               checked>
                                                        <label class="form-check-label"
                                                               for="<?php echo htmlspecialchars($folderId); ?>">
                                                            <?php echo htmlspecialchars($folder); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <p><?php echo Text::_('COM_IMAGELINKER_NO_MEDIA_FOLDERS_FOUND'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php echo $this->form->renderField('ignore_case'); ?>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <?php echo Text::_('COM_IMAGELINKER_SCAN_BUTTON'); ?>
                                        </button>
                                    </div>

                                </div>
                            </div>

                            <?php echo HTMLHelper::_('form.token'); ?>
                            <input type="hidden" name="task" value="imagelinker.scan">
                        </form>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <?php echo Text::_('COM_IMAGELINKER_FORM_NOT_LOADED'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>