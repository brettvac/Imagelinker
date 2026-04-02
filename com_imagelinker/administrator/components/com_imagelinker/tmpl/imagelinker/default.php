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

//FOR DEBUGGING ONLY
use Joomla\CMS\Factory;

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
                        <form action="<?php echo Route::_('index.php?option=com_imagelinker'); ?>"
                              method="post" name="adminForm" id="adminForm" class="form-validate">

                            <div class="fieldset-wrapper">
                               <div class="form-check mb-3">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           id="toggle-all-folders"
                                           checked
                                           onclick="document.querySelectorAll('input[name=\'jform[mediafolders][]\']').forEach(cb => cb.checked = this.checked);">
                                    <label class="form-check-label" for="toggle-all-folders">
                                        <?php echo Text::_('COM_IMAGELINKER_TOGGLE_ALL_FOLDERS'); ?>
                                    </label>
                               </div>
                               <?php echo $this->form->renderField('mediafolders', null, null, ['class' => 'd-flex flex-column']); ?>
                               <?php echo $this->form->renderField('ignore_case'); ?>
                            </div> 

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo Text::_('COM_IMAGELINKER_SCAN_BUTTON'); ?>
                                </button>
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