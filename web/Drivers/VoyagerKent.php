<?php
/**
 * Voyager ILS Driver
 *
 * PHP version 5
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
 * @category VuFind
 * @package  ILS_Drivers
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_ils_driver Wiki
 */

require_once '/www/wwwroot/vufind/web/Drivers/VoyagerRestful.php';

class VoyagerKent extends VoyagerRestful
{

    public function __construct($configFile = 'VoyagerKent.ini')
    {
        // Call the parent's constructor...
        parent::__construct($configFile);

        // Define VoyagerKent Settings
        $this->authFactorType = $this->config['Authentication']['authFactorType'];
        $this->client = new HTTP_Request();
        if (isset($_SESSION['JSESSIONID']))
        {
          $this->client->addCookie('name', 'JSESSIONID', $_SESSION['JSESSIONID']);
        }
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * Completely rewritten for KEVEN purposes.
     * 1) Some bibliographic records can have mixed holdings with and without
     * items (e.g. electronic and print holdings on the same record - journals
     * with electronic and print runs, books in print with eBook holdings too)
     * 2) Needed to retrieve additional data not included in the original
     * function, e.g. item type, bib_format, num copies/vols.
     * 3) Don't display suppressed holdings
     * 4) Display temporary locations where used
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber; on
     * failure, a PEAR_Error.
     * @access public
     */


    //Override main getStatus to include electronic book link
    public function getStatus($id)
    {
        // There are two possible queries we can use to obtain status information.
        // The first (and most common) obtains information from a combination of
        // items and holdings records.  The second (a rare case) obtains
        // information from the holdings record when no items are available.
        
        //See if we have any ebook holdings
        $electronic_data = $this->__getElectronicBookRecords($id);

        $sqlArrayItems = $this->getStatusSQL($id);
        $sqlArrayItems['order'] = array("NVL(LOCATION.LOCATION_DISPLAY_NAME, " .
                "LOCATION.LOCATION_NAME)"); 
        $sqlArrayNoItems = $this->getStatusNoItemsSQL($id);
        $possibleQueries = array(
            $this->buildSqlFromArray($sqlArrayItems),
            $this->buildSqlFromArray($sqlArrayNoItems)
        );

        // Loop through the possible queries and try each in turn -- the first one
        // that yields results will cause us to break out of the loop.
        foreach ($possibleQueries as $sql) {
            // Execute SQL
            try {
                $sqlStmt = $this->db->prepare($sql['string']);
                $sqlStmt->execute($sql['bind']);
            } catch (PDOException $e) {
                return new PEAR_Error($e->getMessage());
            }

            $sqlRows = array();
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $sqlRows[] = $row;
            }

            $data = $this->getStatusData($sqlRows);

            //Munge together ebook records and standard record holdings
            $overall = array_merge($electronic_data, $data);
            // If we found data, we can leave the foreach loop -- we don't need to
            // try any more queries.
            if (count($overall) > 0) {
                break;
            }
        }
        return $this->processStatusData($overall);
    }



    //Quick and dirty function for now to get any holdings details for results on Electronic books.
    //Needs refining when Stewart gets back.
    private function __getElectronicBookRecords($id)
    {

        $sql = "SELECT
                        md.mfhd_id,
                        md.seqnum,
                        mm.display_call_no AS callnumber,
                        mm.location_id,
                        md.record_segment,
                        l.location_display_name AS location
                    FROM
                        $this->dbName.bib_mfhd bm
                    LEFT JOIN
                        $this->dbName.mfhd_master mm ON (bm.mfhd_id = mm.mfhd_id)
                    JOIN
                        $this->dbName.location l ON (mm.location_id = l.location_id)
                    LEFT JOIN
                        $this->dbName.mfhd_data md ON (bm.mfhd_id = md.mfhd_id)
                    WHERE
                        bm.bib_id = :id
                    AND 
                        l.location_display_name='Electronic Book'
                    AND
                        mm.suppress_in_opac='N'
                    ";

        try
        {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute(array(':id' => $id));
        }
        catch (PDOException $e)
        {
                return new PEAR_Error($e->getMessage());
        }

        $holdingsData = array();

        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC))
        {
            if (!isset($holdingsData[$row['MFHD_ID']]))
            {
                $holdingsData[$row['MFHD_ID']] = array
                (
                    'id'          => $id,
                    'mfhd_id'     => $row['MFHD_ID'],
                    'callnumber'  => htmlentities($row['CALLNUMBER']) ? htmlentities($row['CALLNUMBER']) : '',
                    'location'    => htmlentities($row['LOCATION']),
                    'location_id' => $row['LOCATION_ID'],
                    'marc'        => $row['RECORD_SEGMENT'],
                );
            }
            else
            {
                // Concat wrapped rows (MARC data more than 300 bytes is stored in multiple RECORD_SEGMENTS)
                $holdingsData[$row['MFHD_ID']]['marc'] .= $row['RECORD_SEGMENT'];
            }
        }


        return $holdingsData;
    }

    private function __processHoldingRecords($id)
    {

        $sql = "SELECT
                        md.mfhd_id,
                        md.seqnum,
                        mm.display_call_no AS callnumber,
                        mm.location_id,
                        md.record_segment,
                        l.location_display_name AS location
                    FROM
                        $this->dbName.bib_mfhd bm
                    LEFT JOIN
                        $this->dbName.mfhd_master mm ON (bm.mfhd_id = mm.mfhd_id)
                    JOIN
                        $this->dbName.location l ON (mm.location_id = l.location_id)
                    LEFT JOIN
                        $this->dbName.mfhd_data md ON (bm.mfhd_id = md.mfhd_id)
                    WHERE
                        bm.bib_id = :id
                    AND
                        mm.suppress_in_opac='N'
                    ORDER BY
                        l.location_display_name,
                        mm.display_call_no,
                        md.mfhd_id,
                        md.seqnum";

        try
        {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute(array(':id' => $id));
        }
        catch (PDOException $e)
        {
                return new PEAR_Error($e->getMessage());
        }

        $holdingsData = array();

        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC))
        {
            if (!isset($holdingsData[$row['MFHD_ID']]))
            {
                $holdingsData[$row['MFHD_ID']] = array
                (
                    'id'          => $id,
                    'mfhd_id'     => $row['MFHD_ID'],
                    'callnumber'  => htmlentities($row['CALLNUMBER']) ? htmlentities($row['CALLNUMBER']) : 'no_callnumber',
                    'location'    => htmlentities($row['LOCATION']),
                    'location_id' => $row['LOCATION_ID'],
                    'marc'        => $row['RECORD_SEGMENT'],
                );
            }
            else
            {
                // Concat wrapped rows (MARC data more than 300 bytes is stored in multiple RECORD_SEGMENTS)
                $holdingsData[$row['MFHD_ID']]['marc'] .= $row['RECORD_SEGMENT'];
            }
        }

        foreach ($holdingsData as $recordId => &$holdingRecord)
        {
            $holdingRecord = $this->__processMarcRecord($holdingRecord);
            unset($holdingRecord['marc']);
            // Get order information
            $holdingRecord = $this->__processOrderDetails($holdingRecord);

        }

        return $holdingsData;
    }

    private function __processMarcRecord($holdingRecord)
    {
        try
        {
            $marc = new File_MARC(str_replace(array("\n", "\r"), '', $holdingRecord['marc']), File_MARC::SOURCE_STRING);
            if ($record = $marc->next())
            {
            //    Get Public Notes
                if ($fields = $record->getFields('852'))
                {
                    foreach ($fields as $field)
                    {
                        if ($subfields = $field->getSubfields('z'))
                        {
                            foreach($subfields as $subfield)
                            {
                                $holdingRecord['notes'][] = $subfield->getData();
                            }
                        }
                    }
                }

            //    Get Summary (may be multiple lines)
                if ($fields = $record->getFields('866'))
                {
                    foreach ($fields as $field)
                    {
                        if ($subfield = $field->getSubfield('a'))
                        {
                            $holdingRecord['summary'][] = $subfield->getData();
                        }
                    }
                }

            //    Get Electronic links (may be multiple lines)
                if ($fields = $record->getFields('856'))
                {
                    foreach ($fields as $field)
                    {
                        if ($subfield_u = $field->getSubfield('u'))
                        {
                            if ($subfield_z = $field->getSubfield('z'))
                            {
                                $link_text = $subfield_z->getData();
                            } else {
                                $link_text = 'electronic_resource';
                            }
                            $holdingRecord['electronic_link'][] = array($link_text => $subfield_u->getData());
                        }
                    }
                }
              }
        }
        catch (Exception $e)
        {
            trigger_error('Poorly Formatted MFHD Record', E_USER_NOTICE);
        }

       return $holdingRecord;
    }



    private function __processItemRecords($holdingsData)
    {
        $itemData = array();

        $sql = "SELECT
                    mi.item_enum,
                    mi.item_id,
                    ib.item_barcode,
                    it.item_type_display,
                    it.item_type_id,
                    i.on_reserve,
                    i.item_sequence_number,
                    i.copy_number,
                    ist.item_status_desc AS status,
                    pl.location_display_name AS location,
                    tl.location_display_name AS temp_location,
                    pl.location_id AS location_id,
                    tl.location_id AS temp_location_id,
                    ct.current_due_date AS duedate,
                    hri.queue_position AS hold_recall_queue_position,
                    (
                        SELECT
                            TO_CHAR(MAX(circ_trans_archive.discharge_date), 'MM-DD-YY HH24:MI')
                        FROM
                            $this->dbName.circ_trans_archive
                        WHERE
                            circ_trans_archive.item_id = i.item_id) returndate
                FROM
                    $this->dbName.mfhd_item mi
                JOIN
                    $this->dbName.item i ON (mi.item_id = i.item_id)
                JOIN
                    $this->dbName.item_type it ON (i.item_type_id = it.item_type_id)
                JOIN
                    $this->dbName.item_status istat ON (i.item_id = istat.item_id)
                JOIN
                    $this->dbName.item_status_type ist ON (istat.item_status = ist.item_status_type)
                JOIN
                    $this->dbName.item_barcode ib ON (i.item_id = ib.item_id)
                JOIN
                    $this->dbName.location pl ON (i.perm_location = pl.location_id)
                LEFT JOIN
                    $this->dbName.location tl ON (i.temp_location = tl.location_id)
                LEFT JOIN
                    $this->dbName.circ_transactions ct ON (mi.item_id = ct.item_id)
                LEFT JOIN
                    $this->dbName.hold_recall_items hri ON (i.item_id = hri.item_id)
                WHERE
                    mi.mfhd_id = :id
                ORDER BY
                    mi.item_enum,
                    i.copy_number,
                    i.item_sequence_number";

        foreach ($holdingsData as &$holdingRecord)
        {
            try
            {
                $sqlStmt = $this->db->prepare($sql);
                $sqlStmt->execute(array(':id' => $holdingRecord['mfhd_id']));
            }
            catch (PDOException $e)
            {
                return new PEAR_Error($e->getMessage());
            }
        
            $data = array();
            // when there isn't a copy_number, nor an item_sequence_number we need to generate our own number instead. 
            $i= 0;

            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC))
            {
                if (!isset($holdingRecord['items'][$row['ITEM_ID']]))
                {
                    $holdingRecord['items'][$row['ITEM_ID']] = array
                    (
                        'item_id'             => $row['ITEM_ID'],
                        'item_type_id'        => $row['ITEM_TYPE_ID'],
                        'item_type'           => htmlentities($row['ITEM_TYPE_DISPLAY']),
                        'copy'         => $row['COPY_NUMBER'],
                        'temp_location'       => htmlentities($row['TEMP_LOCATION']),
                        'temp_location_id'    => htmlentities($row['TEMP_LOCATION_ID']),
                        'item_type'           => htmlentities($row['ITEM_TYPE_DISPLAY']),
                        'due_date_raw'        => strtotime($row['DUEDATE']),
                        'duedate'             => isset($row['DUEDATE']) ? $this->dateFormat->convertToDisplayDate('m-d-y H:i', $row['DUEDATE']) : NULL,
                        'duetime'             => isset($row['DUEDATE']) ? $this->dateFormat->convertToDisplayTime('m-d-y H:i', $row['DUEDATE']) : NULL,
    
                        'returndate'          => isset($row['RETURNDATE']) ? $this->dateFormat->convertToDisplayDate('m-d-y H:i', $row['RETURNDATE']) : NULL,
                        'returntime'          => isset($row['RETURNDATE']) ? $this->dateFormat->convertToDisplayTime('m-d-y H:i', $row['RETURNDATE']) : NULL,
                        'barcode'             => htmlentities($row['ITEM_BARCODE']),
                        'item_enum'           => htmlentities($row['ITEM_ENUM']),
                        'reserve'             => $row['ON_RESERVE'],
                        'sequence_id'         => $row['ITEM_SEQUENCE_NUMBER'],
                        'status_array'        => array($row['STATUS']),
                        'sort_id'             => $i++,
                    );
    
                    $item = &$holdingRecord['items'][$row['ITEM_ID']];

                    // Process request group
                    $item['temp_location_id']
                        ? $holdingRecord += $this->__processRequestGroupId($item['temp_location_id'])
                        : $holdingRecord += $this->__processRequestGroupId($holdingRecord['location_id']);
    
                    // The same item may be returned in several rows with different
                    // HOLD_RECALL_QUEUE_POSITION, num_hold_recalls needs to be updated
                    // with highest value
                    ($row['HOLD_RECALL_QUEUE_POSITION']
                         && ( !isset($item['num_hold_recalls'])
                             || $item['num_hold_recalls'] < $row['HOLD_RECALL_QUEUE_POSITION']))
                        ? $item['num_hold_recalls'] = $row['HOLD_RECALL_QUEUE_POSITION']
                        : $item['num_hold_recalls'] = 0;
    
                    // Number of copies at this location
                    isset($holdingRecord['num_copies'])
                        ? $holdingRecord['num_copies']++
                        : $holdingRecord['num_copies'] = 1;
                }
                else
                {
                        // The same item may be returned in several rows with different statuses,
                        // STATUS_ARRAY needs to be updated with subsequent statuses
                        $item['status_array'][] = $row['STATUS'];
                }
            }
        }
        return $holdingsData;
    }


    private function __processRequestGroupId($locationId)
    {
        $requestGroup = array();
        $sql = "SELECT
                    rg.group_id AS request_group_id,
                    rg.group_name AS request_group
                FROM
                    $this->dbName.request_group_location rgl
                LEFT JOIN
                    $this->dbName.request_group rg ON (rgl.group_id = rg.group_id)
                WHERE
                    rgl.location_id = :id";
        
        try
        {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute(array(':id' => $locationId));
        }
        catch (PDOException $e)
        {
            return new PEAR_Error($e->getMessage());
        }

        while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC))
        {
            $requestGroup = $row;
        }
        
        return $requestGroup;
    }


    private function __processOrderDetails($holdingRecord)
    {
        if ($holdingRecord['callnumber'] === 'no_callnumber')
        {
            $order_status_sql = "SELECT
                                   lics.item_id,
                                   lics.mfhd_id,
                                   lics.line_item_status,
                                   lics.status_date AS line_item_status_date,
                                   ib.item_barcode AS barcode
                                 FROM
                                   $this->dbName.line_item_copy_status lics
                                 LEFT JOIN
                                   $this->dbName.item_barcode ib ON (lics.item_id = ib.item_id)
                                 WHERE
                                   lics.mfhd_id = :id";
            try
            {
                $sqlStmt = $this->db->prepare($order_status_sql);
                $sqlStmt->execute(array(':id' => $holdingRecord['mfhd_id']));
            }
            catch (PDOException $e)
            {
                return new PEAR_Error($e->getMessage());
            }

            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC))
            {
                if ($row['ITEM_ID'] == 0)
                {
                    $ref = &$holdingRecord['line_item_status']['order_status_' . $row['LINE_ITEM_STATUS']][$this->dateFormat->convertToDisplayDate('m-d-y H:i', $row['LINE_ITEM_STATUS_DATE'])];
                    isset($ref) ? $ref++ : $ref = 1;
                }
                else
                {
                    $ref = &$holdingRecord['items'][$row['ITEM_ID']];
                    $ref['barcode'] = $row['BARCODE'];
                    $ref['item_id'] = $row['ITEM_ID'];
                    $ref['line_item_status'] = 'order_status_' . $row['LINE_ITEM_STATUS'];
                    $ref['line_item_status_date'] = $this->dateFormat->convertToDisplayDate('m-d-y H:i', $row['LINE_ITEM_STATUS_DATE']);
                    $ref['status'] = 'On Order';
                }
            }
        }
        return $holdingRecord;
    }

    private function __formatHoldingsData($holdingsData)
    {
        $nonRecallable = array('Missing', 'Discharged', 'On Order', 'Lost--Library Applied');
        $holdingsArray = array();
        foreach ($holdingsData as $holding)
        {
            if (isset($holding['items']))
            {
                $num_copies_available = &$holding['num_copies_available'];
                $num_copies_available = 0;
                foreach ($holding['items'] as $item_id => $item)
                {
                    $statusArray = isset($item['status_array']) ? $item['status_array'] : array();
                    $availability = $this->determineAvailability($statusArray);
                    // Item availability & count of available
                    $item['availability'] = isset($item['line_item_status']) ? 0 : $availability['available'];
                    $item['availability'] ? $num_copies_available++ : NULL;
                    // Status
                    count($availability['otherStatuses']) > 0
                        ? $item['status'] = $this->__pickStatus($availability['otherStatuses'])
                        : NULL;
                    isset($item['status']) && in_array($item['status'], $nonRecallable)
                        ? $item += array('addLink' => false, 'is_holdable' => false)
                        : NULL;
                    $item += $holding;
                    unset($item['items']);
                    $holdingsArray[] = $item;
                }
            }
            else
            {
                $holding += array(
                    'is_holdable'   => false,
                );
                $holdingsArray[] = $holding;
            }
        }
        return $holdingsArray;
    }

    /**
     * Protected support method to pick which status message to display when multiple
     * options are present.
     *
     * @param array $statusArray Array of status messages to choose from.
     *
     * @return string            The best status message to display.
     * @access protected
     */
    protected function __pickStatus($statusArray)
    {
        // This array controls the rankings of possible status messages.  The lower
        // the ID in the ITEM_STATUS_TYPE table, the higher the priority of the
        // message.  We only need to load it once -- after that, it's cached in the
        // driver.
        // SJB, KEVEN, 18 November 2010: Added the ability for the highest ID to be
        // displayed, ie, if a book is missing and charged, the status missing is displayed
        if ($this->statusRankings == false) {
            // Execute SQL
            $sql = "SELECT * FROM $this->dbName.ITEM_STATUS_TYPE";
            try {
                $sqlStmt = $this->db->prepare($sql);
                $sqlStmt->execute();
            } catch (PDOException $e) {
                return new PEAR_Error($e->getMessage());
            }

            // Read results
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC)) {
                $this->statusRankings[$row['ITEM_STATUS_DESC']]
                    = $row['ITEM_STATUS_TYPE'];
            }
        }

        // Pick the first entry by default, then see if we can find a better match:
        $status = $statusArray[0];
        $rank = $this->statusRankings[$status];
        for ($x = 1; $x < count($statusArray); $x++)
        {
            // SJB, KEVEN, 18 November 2010
            if ($this->config['Statuses']['displayHighestStatusID'])
            {
                if ($this->statusRankings[$statusArray[$x]] > $rank)
                {
                    $status = $statusArray[$x];
                }
            }
            else
            {
                if ($this->statusRankings[$statusArray[$x]] < $rank)
                {
                    $status = $statusArray[$x];
                }
            }
        }

        return $status;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber, duedate,
     * number, barcode; on failure, a PEAR_Error.
     * @access public
     */
    public function getHolding($id, $patron = false)
    {
    //    SJB, 17/7/2010: Function rewritten to allow retrieval of mixed print and electronic holdings (holdings with, and without item records)
        $holdingsData = $this->__processHoldingRecords($id);
        $holdingsData = $this->__processItemRecords($holdingsData);
        $holdingsData = $this->__formatHoldingsData($holdingsData);
        return $holdingsData;
    }


    // KENT: Multivolume works were not showing up correctly. Extra detail from the item-
    //  holdings level was required to prevent being obscured by a distinctrow query.
    //  Additional volume level also needed to inform user that fines are applied to
    //  individual copies.
    public function getFineSQL($patron)
    {
        $template = parent::getFineSQL($patron);
        array_push($template[expressions],"FINE_FEE.FINE_FEE_ID");
        array_push($template[expressions],"FINE_FEE.ITEM_ID");
        array_push($template[expressions],"MFHD_ITEM.ITEM_ENUM");
        array_push($template[expressions],"ITEM.COPY_NUMBER");
        array_push($template[from], $this->dbName.".MFHD_ITEM");
        array_push($template[from], $this->dbName.".ITEM");
        array_push($template[where], "MFHD_ITEM.ITEM_ID = BIB_ITEM.ITEM_ID");
        array_push($template[where], "ITEM.ITEM_ID = BIB_ITEM.ITEM_ID");
        return $template;
    }

    // KENT: Multivolume works were not showing up correctly. Extra detail from the item-
    //  holdings level was required to prevent being obscured by a distinctrow query.
    //  Additional volume level also needed to inform user that fines are applied to
    //  individual copies.
    protected function processFinesData($sqlRow)
    {
        $template = parent::processFinesData($sqlRow);
        $template[item_id] = $sqlRow['ITEM_ID'];
        $template[item_enum] = $sqlRow['ITEM_ENUM'];
        $template[copy_number] = $sqlRow['COPY_NUMBER'];
        return $template;
    }



    /**
     * This handles authenticating a patron and gathering basic information using Voyager
     * web services.  University of Kent LDAP holds Institutional Identifier, instead of
     * barcode, which does not work with core patronLogin function.
     *
     * @param string $patron_id The patron identifier
     * @param string $lname     The patron's last name
     *
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login, PEAR_Error on error.
     * @access public
     */

    public function patronLogin($lname, $patron_id)
    {
        $template = parent::patronLogin($patron_id, $lname);
        $patron = $this->__authenticatePatron($lname, $patron_id);
        if (isset($patron))
        {
            $xmlResponse = $this->__getMyAccountService('PersonalInfoService', $patron);
            $personalInfo = (array) $xmlResponse->children("http://www.endinfosys.com/Voyager/serviceParameters")->children("http://www.endinfosys.com/Voyager/personalInfo");
            $personalName = $personalInfo['name'];
            $patron += array
            (
                'cat_username' => strval($personalName->lastName),
                'email' => strval($personalInfo['emailAddress']->address),
                'cat_password' => $patron_id,
                'firstname' => strval($personalName->firstName),
                'major' => null,
                'college' => null
            );

            return $patron;
        }
        else
        {
            return NULL;
        }
    }

    public function getMyProfile($patron)
    {
        $patron['lastname'] = $patron['lastName'];
        $patron['firstName'] = $patron['firstname'];
        $queryString = 'patron/' . $patron['id'] . '/patronInformation/address?';
        $addressResult = $this->__prepareHttpRestfulRequest('get', $queryString);

        if (isset($addressResult) && isset($addressResult->address))
        {
            $permanentAddress = $addressResult->address->permanentAddress;
            foreach ($permanentAddress->telephone as $phone)
            {
              if ( $patron['phone'] != NULL or $patron['phone'] != '')
              {
                $patron['phone'] .= "; ";
              }
              $patron['phone'] .= "$phone->type: $phone->number";  
            }
            isset($permanentAddress->addressLine1)
                ? $patron['address1'] = strval($permanentAddress->addressLine1)
                : '';
            isset($permanentAddress->addressLine2)
                ? $patron['address2'] = strval($permanentAddress->addressLine2)
                : '';
            isset($permanentAddress->city)
                ? $patron['city'] = strval($permanentAddress->city)
                : '';
            isset($permanentAddress->stateProvince)
                ? $patron['state'] = strval($permanentAddress->stateProvince)
                : '';
            isset($permanentAddress->zipPostal)
                ? $patron['zip'] = strval($permanentAddress->zipPostal)
                : '';
        }
        
        $queryString = 'patron/' . $patron['id'] . '/patronStatus/registrationStatus?';
        $registrationResult = $this->__prepareHttpRestfulRequest('get', $queryString);
        if (isset($registrationResult))
        {
          $patron['group'] = '';
          foreach ($registrationResult->registrationStatus->institution as $institution)
          {
            foreach ($institution->registrationStatus as $patronGroup)
            {
              if ( $patron['group'] != NULL or $patron['group'] != '')
              {
                  $patron['group'] .= "; ";
              }
              $patron['group'] .= $patronGroup->patronGroupName;
            }
          }
        } 
        return $patron;
    }


    public function submitRecall($holdDetails)
    {
    // SJB34, 13 May 2014: Updated to use restful API.  Fix for hold shelf life problems.
    // CVAL thisCopy, anyCopyAt, anyCopy
      $response = array('success' => false, 'status' =>"hold_error_fail"); // set default return response
      $params['requestCode'] = 'RECALL';
      $patronRequests = $this->__getAvailableRequests($holdDetails['id'], $holdDetails['patron']);
      if ( in_array($params['requestCode'], $patronRequests) )
      {
        $queryString = 'record/';
        $queryString .= $holdDetails['id'] . '/';
        if ($holdDetails['recallLevel'] == 'thisCopy')
        {
          $queryString .= 'items/';
          $queryString .= $holdDetails['item_id'] . '/';
          $holdDetails['recallType'] = 'recall-parameters';
        } else {
          $holdDetails['recallType'] = 'recall-title-parameters';
        }
    
        $queryString .= 'recall?patron=' . $holdDetails['patron']['id'];
        $queryString .= '&patron_homedb=' . $holdDetails['patron']['patronHomeUbId'];
        $patronGroups = explode(',', $holdDetails['patron']['group']); // if member of more than 1, only send first
        $queryString .= '&patron_group=' . $patronGroups[0];

        $lastDate = isset($holdDetails['requiredBy'])
          ? strftime('%Y%m%d', strtotime(strval($holdDetails['requiredBy'])))
          : date('Ymd', strtotime(date('Ymd', strtotime(date('Ymd'))) . '+1 month'));

        $queryXML = '<pickup-location>' . htmlentities($holdDetails['pickUpLocation']) . '</pickup-location>' . "\r\n";
        $queryXML .= '<last-interest-date>' . $lastDate . '</last-interest-date>';
        $queryXML .= '<comment>' . htmlentities($holdDetails['comment'], ENT_COMPAT, "UTF-8") . '</comment>';
        $queryXML .= '<dbkey>' . htmlentities($this->ws_dbKey, ENT_COMPAT, "UTF-8") . '</dbkey>';
        $queryXML = '<' . $holdDetails['recallType'] . '>' . $queryXML . '</' . $holdDetails['recallType'] . '>';
        $queryXML = '<?xml version="1.0" encoding="UTF-8"?>' . $queryXML;

        $result = $this->__prepareHttpRestfulRequest('put', $queryString, $queryXML);

        $replyCode = $result->{'reply-code'};
        $replyText = $result->{'create-recall'}
            ? strval($result->{'create-recall'}->{'note'})
            : strval($result->{'create-recall-title'}->{'note'});

        if ( $replyCode == '0' )
        {
          $response['success'] = true;
        } else {
          $response['status'] = $replyText;
        }
      }
      return $response;		
    }

    /**
     * private support method to determine what requests are valid for the patron
     *
     * @param string $bibId the record id for the item being requested
     * @param array  $patron patron data
     * 
     *
     * @return array $requestCodes valid request codes this patron is allowed
     *  to place
     * @access private
     */
    private function __getAvailableRequests($bibId, $patron)
    {
        $params['serviceParameters'] = array
        (
            'bibId'            => $bibId,
            'bibDbCode'        => $this->dbName,
        );
        $requestCodes = array();
        $requests = $this->__getPatronRequestsService($params, $patron);
        $availableRequests = $requests->availableRequests;
        foreach ($availableRequests->requestIdentifier as $requestIdentifier)
        {
            $requestAttributes = (array) $requestIdentifier->attributes();
            extract($requestAttributes['@attributes']);
            array_push($requestCodes, $requestCode);
        }
        return $requestCodes;
    }
    
    private function __getPatronRequestsService($parameters, $patron)
    {
        $xmlRequest = $this->__constructXMLServiceParameters($parameters, $patron);
        $response = $this->__makePostRequest('PatronRequestsService',$xmlRequest);
        $requests = $response->children("http://www.endinfosys.com/Voyager/requests");

        return $requests;
    }

    public function getMyTransactions($patron)
    {
        $transList = array ();
//      Get a list of loans and URI for full information about each
        $queryString = 'patron/' . $patron['id'] . '/circulationActions/loans?view=full';
        $result = $this->__prepareHttpRestfulRequest('get', $queryString);
        if (isset($result) && isset($result->loans->institution->loan))
        {
            foreach ($result->loans->institution->loan as $loan)
            {
                $renewable = array ('Y' => TRUE, 'N' => FALSE);
                $attributes = (array) $loan->attributes();
                extract($attributes['@attributes']);
                $itemId = strval($loan->itemId);
                $bibId = $this->__getBibIds($itemId);
                $transList[$itemId] = array
                (
                    'renewable' => $renewable[strval($canRenew)],
                    'duedate' => date('j M Y (H:i)',strtotime($loan->dueDate)),
                    'id' => $bibId->$itemId,
                    'title' => strval($loan->title),
                    'author' => strval($loan->author),
                    'item_id' => strval($itemId),
                    'status' => strval($loan->statusText),
                    'itemType' => strval($loan->itemtype),
                    'callNumber' => strval($loan->callNumber),
                    'location' => strval($loan->location),
                    'messages' => strval($loan->messages),
                );
            }
        }
//      print_r('<pre>');
// var_dump($transList);exit;
        return $this->__renewalCounts($transList, $patron);
    }

    private function __renewalCounts($transList, $patron)
    {
//      get the number of renewals and the renewal limit for each checked out item
        if (count($transList) > 0)
        {
            $sql = "SELECT
                        ct.renewal_count,
                        cpm.renewal_count as renewal_limit,
                        ct.item_id
                    FROM
                        $this->dbName.circ_transactions ct
                    JOIN
                        $this->dbName.circ_policy_matrix cpm on (ct.circ_policy_matrix_id = cpm.circ_policy_matrix_id)
                    WHERE
                        ct.patron_id = :patron_id";

            try
            {
                $sqlStmt = $this->db->prepare($sql);
                $sqlStmt->execute(array(':patron_id' => $patron['id']));
                while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC))
                {
                    $transList[$row['ITEM_ID']]['renewalCount'] = $row['RENEWAL_COUNT'];
                    $transList[$row['ITEM_ID']]['renewalLimit'] = $row['RENEWAL_LIMIT'];
                }
            }
            catch (PDOException $e)
            {
                return new PEAR_Error($e->getMessage());
            }
        }
        return $transList;
    }

    private function __prepareHttpRestfulRequest($method = 'get', $data, $extra = NULL)
    {
//        Prepares requests for the Voyager RESTful web services, voy 720 -
//         $client = new HTTP_Request('http://' . $this->ws_host . ':' . $this->ws_port . '/' . $this->ws_app . '/' . $data);
        $client = $this->client;
        $client->setURL('http://' . $this->ws_host . ':' . $this->ws_port . '/' . $this->ws_app . '/' . $data);
        switch (strtolower($method))
        {
            case 'post':
                $client->setMethod(HTTP_REQUEST_METHOD_POST);
                $client->clearPostData();
//                 $client->addPostData('Foo','bar');
//                 $client->addRawPostData('Foo=bar&a=b');
                $client->setBody($extra);
                break;
            case 'put':
                $client->setMethod(HTTP_REQUEST_METHOD_PUT);
                $client->clearPostData();
                $client->setBody($extra);
                break;
            default:
                $client->setMethod(HTTP_REQUEST_METHOD_GET);
                $client->clearPostData();
                $client->addQueryString('institution','LOCAL');
                $client->addQueryString('patron_homedb','1@KENTDB20030609162455');
                break;
        }
        
        $response = $this->__httpRestfulRequest($client);
        $client->setURL('http://' . $this->ws_host . ':' . $this->ws_port . '/' . $this->ws_app . '/SessionCleanupService');
        $client->sendRequest();
        $response2 = $client->getResponseBody();
    		$client->disconnect();
	    	unset($client);	
        return $response;
    }

    private function __httpRestfulRequest($client)
    {
//        Sends requests for the Voyager RESTful web services, voy 720 -
        $errorCheck = $client->sendRequest();
        if (PEAR::isError($errorCheck))
        {
            echo 'WXWS connection problem: ' . $errorCheck->toString;
        }
        
        if ($client->getResponseCode() != '200')
        {
            echo 'WXWS request failed: ' . $client->getResponseBody();
        }
        else
        {
            $xmlResponse = $client->getResponseBody();
            return simplexml_load_string($xmlResponse);
        }
    }

    private function __getBibIds($itemIds)
    {
//        expect an array of item_ids to be passed
//        annoyingly Voyager WebServices don't provide the bib_id for the title, only the item_id for the specific copy,and no be service to provide a lookup

        if (is_array($itemIds))
        {
            $itemIds = join(',', $itemIds);
        }

        $sql = "select
                    bi.item_id,
                    bi.bib_id
                from
                    $this->dbName.bib_item bi
                where
                    bi.item_id in (:ids)";
        try
        {
            $sqlStmt = $this->db->prepare($sql);
            $sqlStmt->execute(array(':ids' => $itemIds));
            while ($row = $sqlStmt->fetch(PDO::FETCH_ASSOC))
            {
                $recordIds->$row['ITEM_ID'] = $row['BIB_ID'];
            }
        }
        catch (PDOException $e)
        {
            return new PEAR_Error($e->getMessage());
        }
        return $recordIds;
    }

    /**
     * Submit an ILL (Document Delivery) request
     *
     * @param array $parameters $_POST data from the ILL form
     * @param array $patron patron data
     * 
     *
     * @return string $message  Message if request successful (or not)
     * @access public
     */
    public function submitIllRequest($params, $patron)
    {
        foreach ($params as $key => $value)
        {
            $value = preg_replace('/&(?![A-Za-z0-9#]{1,7};)/','&amp;',$value);
            $params['serviceParameters'][$key] = htmlspecialchars($value,ENT_COMPAT,'UTF-8',false);
        }

        $params['serviceParameters']['bibDbName'] = $this->ws_dbKey;
        $params['serviceParameters']['bibDbCode'] = $this->dbName;
        $params['serviceParameters']['requestSiteId'] = $this->ws_patronHomeUbId;
        
        $xmlRequest = $this->__constructXMLServiceParameters($params, $patron);
        $response = $this->__makePostRequest('SendPatronRequestService', $xmlRequest);
        $message = $response->messages->message->attributes();
        
        if ( $message=='success')
        {
            $message = "IllRequest_success";
        }
        else
        {
            $message = "IllRequest_fail";
        }
        return $message;
    }

    /**
     * This is responsible for authenticating a patron against Voyager using the XML over HTTP web services 
     *
     * @param string $patron_id   The patron identifier
     * @param string $lname       The patron's last name
     *
     * @return mixed              Associative array of patron info on successful login,
     * null on unsuccessful login.
     * @access private
     */

    private function __authenticatePatron($lname, $patron_id)
    {
        $patron = array('cat_username' => $lname, 'cat_password' => $patron_id);
        $xmlRequest = $this->__constructXMLServiceParameters(NULL, $patron);
        $response = $this->__makePostRequest('AuthenticatePatronService',$xmlRequest);
        $xmlError = $response->messages->message;
        if ( isset($xmlError) )
        {
            $errorMessage = isset($xmlError) ? $xmlError->attributes() : NULL;
            return NULL;
        }
        else
        {
            $auth = (array) $response->children("http://www.endinfosys.com/Voyager/patronAuthentication")->attributes();
            $patronGroupArray = (array) $response->children("http://www.endinfosys.com/Voyager/patronAuthentication")->patronGroups->groupIds;
            count($patronGroupArray['id']) > 1 
                ? $patronGroupString = implode(',', $patronGroupArray['id'])
                : $patronGroupString = $patronGroupArray['id'];
    
            extract($auth['@attributes']);
    
            $patron = array
            (
                'id' => $patronId,
                'lastName' => $lastName,
                'patronHomeUbId' => $patronHomeUbId,
                'group' => $patronGroupString
            );
            
            return $patron;
        }
    }

    /**
     *  Gets patron account details using the Voyager APIs 
     *
     * @param string $service    The service being called
     * @param array $patron      keyed patron basic identifiers
     *
     * @return object         XML response from Voyager 
     * 
     * @access private
     */
    private function __getMyAccountService($service, $patron)
    {
        $response = file_get_contents('http://' . $this->ws_host . ':' . $this->ws_port . '/' . $this->ws_app . '/' . $service . '?patronId=' . $patron['id'] . '&patronHomeUbId=' . $this->ws_patronHomeUbId);
        $xmlResponse = simplexml_load_string($response);

        return $xmlResponse;
    }

    /**
     * This is used to construct the <ser:patronIdentifier > string used in the
     *  Voyager XML over HTTP web services 
     *
     * @param array $patron  Keyed patron identifiers
     *
     * @return string        XML 
     * 
     * @access private
     */
    private function __constructXMLPatronIdentifier($patron)
    {
        $xml  = '<ser:patronIdentifier lastName="' . $patron['cat_username']
         . '" patronHomeUbId="' . $this->ws_patronHomeUbId;
        $xml .= isset($patron['id'])
            ? '" patronId="' . $patron['id'] . '">'
            : '">';
        $xml .= '<ser:authFactor type="' . $this->authFactorType . '">'
         . $patron['cat_password']
         . '</ser:authFactor></ser:patronIdentifier>';

        return $xml;
    }

    /**
     * This is used to construct the XML string to be posted to the
     *  Voyager XML over HTTP web services 
     *
     * @param array   $patron  Keyed patron identifiers
     * @param array   $params  Keyed parameters to construct XML message
     *
     * @return string        XML 
     * 
     * @access private
     */
    private function __constructXMLServiceParameters($params = NULL, $patron = NULL)
    {
        $xml = '';
        if (isset($params['serviceParameters']) && count($params['serviceParameters']) > 0)
        {
            foreach ($params['serviceParameters'] as $key => $value)
            {
                $xml .= '<ser:parameter key="' . $key . '"><ser:value>' . $value . '</ser:value></ser:parameter>';
            }

            $xml = '<ser:parameters>' . $xml . '</ser:parameters>';
        }
        else
        {
            $xml .= '<ser:parameters/>';
        }

        if ($patron)
        {
            $xml .= $this->__constructXMLPatronIdentifier($patron);
        }

        if (isset($params['definedParameters']) && count($params['definedParameters']) > 0)
        {
            $xml .= '<ser:definedParameters xsi:type="'
                 . $params['definedParameters']['xsi:type']
                 . '" xmlns:myac="'
                 . $params['definedParameters']['xmlns:myac']
                 . '" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';

            foreach ($params['definedParameters']['identifiers'] as $identifier)
            {
                $xml .= '<myac:' . $params['definedParameters']['identifierType'] . '>';

                foreach ($identifier as $key => $value)
                {
                    $xml .= '<myac:' . $key . '>' . $value . '</myac:' . $key . '>';
                }

                $xml .= '</myac:' . $params['definedParameters']['identifierType'] . '>';        
            }

            $xml .= '</ser:definedParameters>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <ser:serviceParameters xmlns:ser="http://www.endinfosys.com/Voyager/serviceParameters">'
             . $xml
             . '</ser:serviceParameters>';    
        return $xml;
    }

    /**
     * This is used for sending requests to Voyager XML over HTTP web services 
     *
     * The Voyager XML over HTTP web service expect POST requests
     *
     * @param string $service The service being called
     * @param string $xml     The XML string with the service parameters
     *
     * @return object         XML response from Voyager 
     * 
     * @access private
     */
    private function __makePostRequest($service,$xml)
    {
//         $client = new HTTP_Request('http://' . $this->ws_host . ':' . $this->ws_port . '/' . $this->ws_app . '/' . $service);
        $client = $this->client;
        $client->setURL('http://' . $this->ws_host . ':' . $this->ws_port . '/' . $this->ws_app . '/' . $service);
        $client->setMethod(HTTP_REQUEST_METHOD_POST);
//         $client->addHeader('Content-Type', 'application/x-www-form-urlencoded\r\n');
        $client->clearPostData();
        $client->addRawPostData($xml);
        $client->sendRequest();
        $response = $client->getResponseBody();
        $client->setURL('http://' . $this->ws_host . ':' . $this->ws_port . '/' . $this->ws_app . '/SessionCleanupService');
        $client->sendRequest();
        $response2 = $client->getResponseBody();
        $disconnect = $client->disconnect();
        unset($client);
        $xmlResponse = simplexml_load_string($response);
        $serviceParameters = $xmlResponse->children("http://www.endinfosys.com/Voyager/serviceParameters");
        return $serviceParameters;
    }
}
?>
