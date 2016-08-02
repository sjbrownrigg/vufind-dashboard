<?php
/**
 *
 * Copyright (C) Villanova University 2007.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * This page: Stewart J Brownrigg, April 2010
 *
 */

require_once 'services/MyResearch/MyResearch.php';

class IllRequest extends MyResearch
{
    function launch()
    {
        global $configArray;
        global $interface;
        global $user;

        $forms = array
        (
            'DDBLANK' => array
            (
                'title'		=> translate('Document delivery book'),
                'fields' 	=> array
                (
                    'FF0'	=> array
                    (
                        'fieldName'	=> translate('Borrower'),
                        'attributes'	=> array('readonly','borrowerName')
                    ),
                    'FF1'	=> array
                    (
                        'fieldName'	=> translate('Title'),
                        'attributes'	=> array('required')
                    ),
                    'FF2'	=> array
                    (
                        'fieldName'	=> translate('Author'),
                        'attributes'	=> array()
                    ),
                    'FF3'	=> array
                    (
                        'fieldName'	=> translate('Publisher'),
                        'attributes'	=> array()
                    ),
                    'FF4'	=> array
                    (
                        'fieldName'	=> translate('Date of publication'),
                        'attributes'	=> array()
                    ),
                    'FF5'	=> array
                    (
                        'fieldName'	=> translate('ISBN'),
                        'attributes'	=> array()
                    ),
                    'FF6'	=> array
                    (
                        'fieldName'	=> translate('School name'),
                        'attributes'	=> array('required'),
                        'list'		=> array('costCode' => 'Cost code text',)
                    ),
                    'FF7'	=> array
                    (
                        'fieldName'	=> translate('Email'),
                        'attributes'	=> array('readonly', 'borrowerEmail')
                    ),
                    'FF8'	=> array
                    (
                        'fieldName'	=> translate('Campus address'),
                        'attributes'	=> array('textarea')
                    ),
                    'FF9'	=> array
                    (
                        'fieldName'	=> translate('Supervisor'),
                        'attributes'	=> array('required')
                    ),
                    'PICK'	=> array
                    (
                        'fieldName'	=> translate('Collection point'),
                        'attributes'	=> array('required', 'list'),
                        'list'		=> array('Core Text Collection' => 'Core Text Collection',)
                    ),
                )
            ),
            'COPYBLANK' => array
            (
                'title'		=> translate('Document delivery article/book chapter'),
                'fields' 	=> array
                (
                    'FF0'	=> array
                    (
                        'fieldName'	=> translate('Borrower'),
                        'attributes'	=> array('readonly','borrowerName')
                    ),
                    'FF1'	=> array
                    (
                        'fieldName'	=> translate('Journal or book title'),
                        'attributes'	=> array('required')
                    ),
                    'FF2'	=> array
                    (
                        'fieldName'	=> translate('Article or chapter title'),
                        'attributes'	=> array('required')
                    ),
                    'FF3'	=> array
                    (
                        'fieldName'	=> translate('Article or chapter author'),
                        'attributes'	=> array()
                    ),
                    'FF4'	=> array
                    (
                        'fieldName'	=> translate('Date published'),
                        'attributes'	=> array('required')
                    ),
                    'FF5'	=> array
                    (
                        'fieldName'	=> translate('Volume & issue number'),
                        'attributes'	=> array()
                    ),
                    'FF6'	=> array
                    (
                        'fieldName'	=> translate('Pages'),
                        'attributes'	=> array()
                    ),
                    'FF7'	=> array
                    (
                        'fieldName'	=> translate('School name'),
                        'attributes'	=> array('required'),
                        'list'		=> array('costCode' => 'Cost code text',)
                    ),
                    'FF8'	=> array
                    (
                        'fieldName'	=> translate('Email'),
                        'attributes'	=> array('readonly', 'borrowerEmail')
                    ),
                    'FF9'	=> array
                    (
                        'fieldName'	=> translate('Campus address'),
                        'attributes'	=> array('textarea')
                    ),
                    'FF10'	=> array
                    (
                        'fieldName'	=> translate('Supervisor'),
                        'attributes'	=> array('required')
                    ),
                    'PICK'	=> array
                    (
                        'fieldName'	=> translate('Collection point'),
                        'attributes'	=> array('required', 'list'),
                        'list'		=> array('Core Text Collection' => 'Core Text Collection',)
                    ),
                )
            ),
        );

		if (!isset($_POST['requestCode']))
		{
			$requestCode = $_GET['requestCode'];
			$form = $forms[$requestCode];
			$borrowerName = $user->firstname . ' ' . $user->lastname;
			$borrowerEmail = $user->email;
			$interface->assign('form', $form);
			$interface->assign('borrowerName', $borrowerName);
			$interface->assign('borrowerEmail', $borrowerEmail);
			$interface->assign('requestCode', $requestCode);
		}
		else
		{
			$request = $_POST;
			$patron = $this->catalog->patronLogin($user->cat_username, $user->cat_password);
 			$result = $this->catalog->submitIllRequest($_POST, $patron);

 			//KENT - Send reciept of document delivery			
			$to = $user->email;
			$subject = translate('doc_del_email_title');
			$message = translate($result) . "\r\n\r\n";
			$from = translate('doc_del_email_from');
			$headers = "From:" . $from . "\r\n";
            $whitelist = $forms[$request['requestCode']]['fields'];
            
            foreach ($request as $key => $value)
            {
                if ( $whitelist[$key] )
                {
                    $message .= $whitelist[$key]['fieldName'] . ": $value\r\n";
                }
            }
            
            if ( $result == "IllRequest_fail" )
            {
    			$bcc = "documentdelivery@kent.ac.uk\r\n";  // send a copy to DocDel for troubleshooting
    			$headers .= "Bcc:" . $bcc;
            }

			mail($to,$subject,$message,$headers);
			//KENT - End of send

			$interface->assign('result', translate($result));
		}

        $interface->setTemplate('ill-request.tpl');
        $interface->setPageTitle(translate('IllRequest'));
        $interface->display('layout.tpl');
    }
}
?>