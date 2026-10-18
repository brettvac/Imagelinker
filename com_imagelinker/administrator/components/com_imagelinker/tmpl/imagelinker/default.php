<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Imagelinker
 * @version     1.5
 * @license     GNU General Public License version 2
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
                    <form action="<?php echo Route::_('index.php?option=com_imagelinker'); ?>"
                          method="post"
                          name="adminForm"
                          id="adminForm"
                          class="form-validate">


                        <div class="fieldset-wrapper">
                            <div class="form-check mb-2">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="toggle-all-folders"
                                       onclick="document.querySelectorAll('input[name=\'jform[mediafolders][]\']').forEach(cb => cb.checked = this.checked); document.getElementById('toggle-subfolders').checked = this.checked;">

                                <label class="form-check-label" for="toggle-all-folders">
                                    <?php echo Text::_('COM_IMAGELINKER_TOGGLE_ALL_FOLDERS'); ?>
                                </label>
                            </div>

                            <div class="form-check mb-3">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="toggle-subfolders"
                                       onclick="
                                           const checkboxes = [...document.querySelectorAll('input[name=\'jform[mediafolders][]\']')];
                                           const selectedFolders = checkboxes.filter(cb => cb.checked).map(cb => cb.value);

                                           checkboxes.forEach(cb => {
                                               const parent = cb.value.substring(0, cb.value.lastIndexOf('/'));

                                               if (parent && selectedFolders.includes(parent)) {
                                                   cb.checked = this.checked;
                                               }
                                           });
                                       ">

                                <label class="form-check-label" for="toggle-subfolders">
                                    <?php echo Text::_('COM_IMAGELINKER_TOGGLE_SUBFOLDERS'); ?>
                                </label>
                            </div>

                            <?php echo $this->form->renderField(
                                'mediafolders',
                                null,
                                null,
                                ['class' => 'd-flex flex-column']
                            ); ?>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <?php echo Text::_('COM_IMAGELINKER_SCAN_BUTTON'); ?>
                            </button>
                        </div>

                        <?php echo HTMLHelper::_('form.token'); ?>
                        <input type="hidden" name="task" value="imagelinker.scan">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>