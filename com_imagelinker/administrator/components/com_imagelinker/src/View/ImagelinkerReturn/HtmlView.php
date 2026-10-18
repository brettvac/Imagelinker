<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Imagelinker
 * @version     1.5
 * @license     GNU General Public License version 2
 */

namespace Naftee\Component\Imagelinker\Administrator\View\ImagelinkerReturn;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;

class HtmlView extends BaseHtmlView
{
    protected $unlinkedImages;
    protected $processedImages;
    protected $params;

    public function display($tpl = null)
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();
        
        if (!$user->authorise('core.manage', 'com_imagelinker'))
        {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            return false;
        }
              
        // Get the data from the user state (populated by the Controller's scan function)
        $data = $app->getUserState('com_imagelinker.data', new \stdClass);
        $this->unlinkedImages = $data->unlinked_images ?? [];
        $this->processedImages = $data->processed_images ?? [];
        
        // Fetch the configuration parameters inside config.xml
        $this->params = ComponentHelper::getParams('com_imagelinker');
        
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_IMAGELINKER_RESULTS_TITLE'), 'image imagelinker');
        
        ToolbarHelper::cancel('imagelinker.cancel', 'JTOOLBAR_CANCEL');

    }
}