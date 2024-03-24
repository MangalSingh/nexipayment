<?php
/**
 * @version            1.2.0
 * @package            Joomla
 * @subpackage         Event Booking
 * @author             Mangal
 * @copyright          Copyright (C) 2010 - 2024 Octocub Team
 * @license            GNU/GPL, see LICENSE.php
 */
// no direct access
defined('_JEXEC') or die;

class nexi_payments extends RADPayment
{

	/**
	 * Constructor functions, init some parameter
	 *
	 * @param object $params
	 */
	public function __construct($params, $config = array())
	{
		parent::__construct($params, $config);
		if ($params->get('mode'))
		{
			$this->url = 'https://ecommerce.nexi.it/ecomm/ecomm/DispatcherServlet
';
		}
		else
		{
			$this->url = 'https://int-ecommerce.nexi.it/ecomm/ecomm/DispatcherServlet
';
		}
		$this->ALIAS = $params->get('ALIAS');
		$this->CHIAVESEGRETA = $params->get('CHIAVESEGRETA');
		$this->setParameter('alias', $this->ALIAS);
	}

	/**
	 * Process Payment
	 *
	 * @param object $row
	 * @param array  $data
	 */
	public function processPayment($row, $data)
	{
	    $Itemid  = JFactory::getApplication()->input->getInt('Itemid', 0);
		$importo = round($data['amount'], 2);
		$this->setParameter('importo',$importo );
		$this->setParameter('divisa', "EUR");
		$codTrans = "TESTPS_" . date('YmdHis');
		$mac = sha1('codTrans=' . $codTrans . 'divisa=EURimporto=' . $importo . $this->CHIAVESEGRETA);
		$this->setParameter('codTrans', $codTrans);
		$siteUrl = JUri::base();
		$this->setParameter('url', $siteUrl . 'index.php?option=com_eventbooking&task=payment_confirm&payment_method=nexi_payments&id=' . $row->id . '&Itemid=' . $Itemid);
		$this->setParameter('url_back', $siteUrl . 'index.php?option=com_eventbooking&view=failure&id=' . $row->id . '&Itemid=' . $Itemid);
		$this->setParameter('mac', $mac);
		$this->renderRedirectForm();
	}

	/**
	 * Verify payment
	 *
	 * @return bool
	 */
	public function verifyPayment()
	{
	    $app       = JFactory::getApplication();
	    $Itemid    = $app->input->getInt('Itemid', 0);
		$ret       = $this->validate();
		$id        = (int) $this->notificationData['trackid'];
		$paymentId = $this->notificationData['paymentid'];
		
		if ($this->validate())
		{
			$id             = $this->notificationData['id'];
			$transactionId  = $this->notificationData['codTrans'];
			$status 		= $this->notificationData['esito'];
// 			echo "<pre>";
// 			print_r($this->notificationData);

			if($status == "OK"){

				$row = JTable::getInstance('EventBooking', 'Registrant');
				$row->load($id);
				if (!$row->id)
				{
					return false;
				}

				if ($row->published)
				{
					return false;
				}

				$this->onPaymentSuccess($row, $transactionId);
			    echo "REDIRECT=" . JUri::getInstance()->toString(array('scheme', 'user', 'pass', 'host')) . JRoute::_('index.php?option=com_eventbooking&view=complete&registration_code=' . $row->registration_code . '&Itemid=' . $Itemid . '&paymentid=' . $paymentId, false, false);
			    JFactory::getApplication()->redirect(JRoute::_('index.php?option=com_eventbooking&view=complete&registration_code=' . $row->registration_code . '&Itemid=' . $Itemid . '&transactionId=' . $transactionId, false, false));
			    $app->close();
			}
			else
    		{
    			echo "REDIRECT=" . JUri::getInstance()->toString(array('scheme', 'user', 'pass', 'host')) . JRoute::_('index.php?option=com_eventbooking&view=failure&id=' . $id . '&Itemid=' . $Itemid . '&paymentid=' . $paymentId, false, false);
    			JFactory::getApplication()->redirect(JRoute::_('index.php?option=com_eventbooking&view=failure&id=' . $row->id . '&Itemid=' . $Itemid, false, false));
    			$app->close();
    		}
		}
		else
		{
			echo "REDIRECT=" . JUri::getInstance()->toString(array('scheme', 'user', 'pass', 'host')) . JRoute::_('index.php?option=com_eventbooking&view=failure&id=' . $id . '&Itemid=' . $Itemid . '&paymentid=' . $paymentId, false, false);
			$app->close();
		}
	}


	/**
	 * Validate the post data from Payment gateway to our server
	 *
	 * @return string
	 */
	protected function validate()
	{

		$this->notificationData = $_REQUEST;
		$requiredParams = array('codTrans', 'esito', 'importo', 'divisa', 'data', 'orario', 'codAut', 'mac');
		foreach ($requiredParams as $param) {
		    if (!isset($_REQUEST[$param])) {
		        return false;
		    }
		}

		// Calcolo MAC con i parametri di ritorno
		$macCalculated = sha1('codTrans=' . $_REQUEST['codTrans'] .
		        'esito=' . $_REQUEST['esito'] .
		        'importo=' . $_REQUEST['importo'] .
		        'divisa=' . $_REQUEST['divisa'] .
		        'data=' . $_REQUEST['data'] .
		        'orario=' . $_REQUEST['orario'] .
		        'codAut=' . $_REQUEST['codAut'] .
		        $this->CHIAVESEGRETA
		);

		// Verifico corrispondenza tra MAC calcolato e parametro mac di ritorno
		if ($macCalculated != $_REQUEST['mac']) {
		    return false;
		}

		// Validate the callback data, return true if it is valid and false otherwise
		return true;
	}
}