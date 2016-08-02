<?php
/**
 *
 * Copyright (C) Villanova University 2010.
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
 */
require_once 'File/MARC.php';

require_once 'RecordDrivers/MarcRecord.php';

class KentRecord extends MarcRecord
{

	/*
	* Extend the metadata provided by this function
	* Get electronic links from the index
	*/
    public function getSearchResult($view = 'list')
    {
    	global $interface;

    	$template = parent::getSearchResult();
    	$interface->assign('summEdition', $this->getEdition());
    	$interface->assign('summReprintDate', $this->getReprintDate());
   	
    	if (!isset($configArray['OpenURL']['replace_other_urls']) ||
            !$configArray['OpenURL']['replace_other_urls'] || !$hasOpenURL) {
            $interface->assign('summURLs', $this->getEResource());
        } else {
            $interface->assign('summURLs', array());
        }
    	return $template;
    }


	public function getHoldings()
	{
		global $interface;

		$template = parent::getHoldings();
		$interface->assign('holdingsURLs', $this->getFullTextURLs());
    	$interface->assign('holdingsIssuedRun', $this->getIssuedRun());
    	$interface->assign('holdingsLibraryLacks', $this->getLibraryLacksInfo());

		return $template;
	}


	public function getCoreMetadata()
	{
		global $interface;
		global $patron;

		$template = parent::getCoreMetadata();
		$interface->assign('coreURLs', $this->getFullTextURLs());
  	$interface->assign('coreIssuedRun', $this->getIssuedRun());
    $interface->assign('corePublications', $this->getPublicationDetails());
    $interface->assign('coreRDAPublications', $this->getRDAPublicationDetails());
    $interface->assign('issn', $this->getCleanISSN());
    $interface->assign('issuesHeld', $this->getIssuedRun());
    $interface->assign('extendedNotes', $this->getGeneralNotes());
		$interface->assign('extendedAcquisitionSource', $this->getAcquisitionSource());
		$interface->assign('extendedShelfReady', $this->getShelfReady());
		$interface->assign('extendedHoldings', $this->getRealTimeHoldings($patron));

		return $template;
	}

	/*
	* Extend the meatadata provided by this function
	*/
	public function getExtendedMetadata()
	{
		global $interface;

		$template = parent::getExtendedMetadata();
		$interface->assign('extendedURLs', $this->getURLs());
		$interface->assign('extendedDissertationNotes', $this->getDissertationNotes());
		$interface->assign('extendedRelatedTitles', $this->getRelatedTitles());
		$interface->assign('extendedEdition', $this->getEdition());
 		$interface->assign('extendedAlternativeTitles', $this->getAlternativeTitles());
 		$interface->assign('extendedFrequency', parent::getPublicationFrequency());
 		$interface->assign('extendedAccessionNumber', $this->getAccessionNumber());
    $interface->assign('extendedMainAuthor', $this->getExtendedPrimaryAuthor());
    $interface->assign('extendedCorporateAuthor', $this->getExtendedCorporateAuthor());
    $interface->assign('extendedAddedCorporateAuthor', $this->getExtendedAddedCorporateAuthor());
    $interface->assign('extendedContributors', $this->getExtendedContributors());
    $interface->assign('extendedConference', $this->getExtendedConference());
    $interface->assign('extendedAddedConference', $this->getExtendedAddedConference());
    $interface->assign('extendedMainUniformTitle', $this->getExtendedMainUniformTitle());
    $interface->assign('extendedPublication', $this->getExtendedPublication());
    $interface->assign('extendedSeries', $this->getSeries());
    $interface->assign('extendedGenre', $this->getExtendedGenre());
    $interface->assign('extendedWithNote', $this->getExtendedWithNote());
		$interface->assign('extendedScale', $this->getExtendedScale());
		$interface->assign('coreSubjects', $this->getAllSubjectHeadings());
		$interface->assign('extendedRelatedPublications', $this->getRelatedPublications());
		$interface->assign('allNotes', $this->getAllNotes());
		$interface->assign('moduleCodes', $this->getModuleCodes());
		$interface->assign('extendedGenreKent', $this->getExtendedGenreKent());
		return $template;
    }

	/*
	* Extend the metadata provided by this function
	*/
	public function getListEntry($user, $listId = null, $allowEdit = true)
	{
		global $interface;

		$template = parent::getListEntry($user, $listId = null, $allowEdit = true);
		$interface->assign('listCallnumber', $this->getCallnumbers());

		return $template;
    }



    /**
     * Return an associative array of URLs associated with this record (key = URL,
     * value = description).
     * This version fetches URLs from the MFHD record, indexed as eResource
     *
     * @access  protected
     * @return  array
     */
    protected function getEResource()
    {
        $urls = array();
        if (isset($this->fields['eResource']) && is_array($this->fields['eResource'])) {
            foreach($this->fields['eResource'] as $eResource) {
                list($text,$url,$note) = explode('|',$eResource);
                // The index doesn't contain descriptions for URLs, so we'll just
                // use the URL itself as the description.
                $urls[$url] = $text;
            }
        }
        return $urls;
    }

	/*
	* The built-in version of this function does not include link text from 856 $3
	*/
    protected function getURLs()
    {
        $retVal = array();

        $urls = $this->marcRecord->getFields('856');
        if ($urls)
        {
            foreach ($urls as $url)
            {
                // Is there an address in the current field?
                $address = $url->getSubfield('u');
                if ($address)
                {
                    $address = $address->getData();

                    // Is there a description?  If not, just use the URL itself.
					switch ( true )
					{
						case $desc = $url->getSubfield('z'):
	                        $desc = $desc->getData();
    	                    break;
						case $desc = $url->getSubfield('3'):
	                        $desc = $desc->getData();
    	                    break;
						case $desc = $url->getSubfield('y'):
	                        $desc = $desc->getData();
    	                    break;
    	                default:
	                        $desc = $address;
					}
                    $retVal[$address] = $desc;
                }
            }
        }
        return $retVal;
    }



    protected function getFullTextURLs()
    {
        $retVal = array();

        $fields = $this->marcRecord->getFields('856');
        if ($fields)
        {
            foreach ($fields as $field)
            {
                $indicator1 = $field->getIndicator('1');
                $indicator2 = $field->getIndicator('2');

                if ( $indicator1 == 4 and $indicator2 == 0 )
                {
                    // Is there an address in the current field?
                    $address = $field->getSubfield('u');
                    if ($address)
                    {
                        $address = $address->getData();
    
                        // Is there a description?  If not, just use the URL itself.
                        switch ( true )
                        {
                            case $desc = $field->getSubfield('z'):
                                $desc = $desc->getData();
                                break;
                            case $desc = $field->getSubfield('3'):
                                $desc = $desc->getData();
                                break;
                            case $desc = $field->getSubfield('y'):
                                $desc = $desc->getData();
                                break;
                            default:
                                $desc = $address;
                        }
                        $retVal[$address] = $desc;
                    }
                }
            }
        }
        return $retVal;
    }



    
	protected function getCallnumbers()
	{
		$retVal = array();
		$holdings = $this->marcRecord->getFields('999');
		if ($holdings)
		{
			foreach ( $holdings as $holding )
			{
				$callnumber = $holding->getSubfield('c');
				if ($callnumber)
				{
					$retVal[] = $callnumber->getData();
				}
			}
		}
		return $retVal;
    }


	/*
		Function to get issued run information (362) from marc21 data
		The University of Kent deviates from prescribed practice
		and uses this field to reflect local holdings

		@access  protected
		@return  array
     */

	protected function getIssuedRun()
	{
		$retVal = array();
		$fields = $this->marcRecord->getFields('362');
		if ($fields)
		{
			foreach ( $fields as $field )
			{
	            $data = '';
	            $subfields = $field->getSubfields();
	            foreach($subfields as $subfield)
	            {
	                $data .= $subfield->getData();
            	}
				$retVal[] = $data;
			}
		}
		return $retVal;
    }

	protected function getAllNotes()
	{
		$retVal = array();
		$fields = $this->marcRecord->getFields('^5..$', true);
		if ($fields)
		{
			foreach ( $fields as $field )
			{
				$retVal[] = $field;
			}
		}
		return $retVal;
    }

	protected function getModuleCodes()
	{
		$retVal = array();
		$fields = $this->marcRecord->getFields('990');
		if ($fields)
		{
			foreach ( $fields as $field )
			{
				$retVal[] = $field;
			}
		}
		return $retVal;
    }


	/*
		Related titles (740)

		@access  protected
		@return  array
     */

	protected function getRelatedTitles()
	{
		$retVal = array();
		$fields = $this->marcRecord->getFields('740');
		if ($fields)
		{
			foreach ( $fields as $field )
			{
	            $data = '';
	            $subfields = $field->getSubfields();
	            foreach($subfields as $subfield)
	            {
	                $data .= $subfield->getData();
            	}
				$retVal[] = $data;
			}
		}
		return $retVal;
    }

    /**
     * Get library lacks information from 500 field.
     *
     * @access  protected
     * @return  array
     */
    protected function getLibraryLacksInfo()
    {
		$retVal = array();
        $fields = $this->marcRecord->getFields('500');

		if ($fields)
		{
			foreach ($fields as $field)
			{
	            $data = '';
	            $subfields = $field->getSubfields();
	            foreach($subfields as $subfield)
	            {
	                $string = $subfield->getData();
	                if (preg_match('/lacks/i', $string))
	                {
	                	$data .= $string;
	                }
            	}
            	if (trim($data) != ""){
            		$retVal[] = $data;
            	}
					
			}
		}		

		return $retVal;
    }


    /**
     * Get an array of dissertation notes for the record.
     *
     * @access  protected
     * @return  array
     */
    protected function getDissertationNotes()
    {
      $retVal = array();
      $fields = $this->marcRecord->getFields('502');
      if ($fields)
      {
        foreach ($fields as $field)
        {
          $data = '';
          $subfields = $field->getSubfields();
          foreach($subfields as $subfield)
          {
            $data .= $subfield->getData();
          }
          $retVal[] = $data;
        }
      }
      return $retVal;
    }

    /**
     * Get an array of alternative titles for the record.
     *
     * @access  protected
     * @return  array
     */
    protected function getAlternativeTitles()
    {
		$retVal = array();
        $fields = $this->marcRecord->getFields('246');
		if ($fields)
		{
			foreach ($fields as $field)
			{
	            $data = '';
	            $subfields = $field->getSubfields();
	            foreach($subfields as $subfield)
	            {
	                $data .= $subfield->getData();
            	}
				$retVal[] = $data;
			}
		}
		return $retVal;
    }

     /**
     * Get an array of alternative titles for the record.
     *
     * @access  protected
     * @return  array
     */
    protected function getRelatedPublications()
    {
      $retVal = array();
      $fields = $this->marcRecord->getFields('580|740');
      if ($fields)
      {
        foreach ($fields as $field)
        {
          $data = '';
          $subfields = $field->getSubfields();
          foreach($subfields as $subfield)
          {
            $data .= $subfield->getData();
          }
          $retVal[] = $data;
        }
      }
      return $retVal;
    }   

    protected function getExtendedGenreKent()
    {
      $retVal = array();
      $fields = $this->marcRecord->getFields('655');
      if ($fields)
      {
        foreach ($fields as $field)
        {
          $data = '';
          $subfields = $field->getSubfields();
          foreach($subfields as $subfield)
          {
            $retVal[] = $subfield->getData();
          }
        }
        return $retVal;
      }
    }

    /**
    * Get the RDA publication details for the record from the 264 field.
    *
    * @access  protected
    * @return  array
    **/

    protected function getRDAPublicationDetails()
    {

      $ind1 = array (
        " " => "",
        "0" => "",
        "2" => "Intervening",
        "3" => "Current/latest"
      );

      $ind2 = array (
        " " => "publication", //default
        "0" => "production",
        "1" => "publication",
        "2" => "distribution",
        "3" => "manufacture",
        "4" => "copyright notice date"
      );

      $retval = array();

   		$fields = $this->marcRecord->getFields('264');
  		if ($fields)
  		{
		  	foreach ( $fields as $field )
		  	{
          $indicator1 = $ind1{$field->getIndicator('1')};
          $indicator2 = $ind2{$field->getIndicator('2')};
          $displayLabel = $indicator1  ? $indicator1 . ' ' . $indicator2 : $indicator2;
				  $places = $field->getSubfield('a');
				  $names = $field->getSubfield('b');
				  $dates = $field->getSubfield('c');
          if ( $places || $names || $dates )
          {
            $imprint = $places ? $places->getData() . ' ' : ''; 
            $imprint .= $names ? $names->getData() . ' ' : '';
            $imprint .= $dates ? $dates->getData(). ' ' : '';
            $imprint = str_replace(chr('194'),'',$imprint); // get rid of 'Â' character 
            $imprint = preg_replace('/\s\s+/', ' ', trim($imprint));
            $retval[] = array (
                            "displayLabel" => ucfirst($displayLabel),
                            "imprint"      => $imprint
                            );
          }
        }
      }
      return $retval;
   }

    /**
    * Get the publication details for the record.
    *
    * @access  protected
    * @return  array
    */

    protected function getPublicationDetails()
    {
	//  Replaced original function as unable to append extra reprint date easily

        $places = $this->getPlacesOfPublication();
        $names = $this->getPublishers();
        $dates = $this->getPublicationDates();
        $reprints = $this->getReprintDate();
        
        $i = 0;
        $retval = array();
        while (isset($places[$i]) || isset($names[$i]) || isset($dates[$i]) || isset($reprints[$i])) {
            // Put all the pieces together, and do a little processing to clean up
            // unwanted whitespace.
            $retval[] = trim(str_replace('  ', ' ', 
                ((isset($places[$i]) ? $places[$i] . ' ' : '') .
                (isset($names[$i]) ? $names[$i] . ' ' : '') .
                (isset($dates[$i]) ? $dates[$i] : '') .
                (isset($reprints[$i]) ? ' ' . $reprints[$i] : ''))));
            $i++;
         }
        return $retval;
    }

    /**
     * Get the item's places of publication.
     *
     * @return array
     * @access protected
     */
    protected function getPlacesOfPublication()
    {
        $subfields = array('a');
        $fields = $this->getFieldArray('260', $subfields);
        return $fields;
    }

    /**
     * Get the item's publication dates.
     *
     * @return array
     * @access protected
     */
    protected function getPublicationDates()
    {
        $subfields = array('c');
        $fields = $this->getFieldArray('260', $subfields);
        return $fields;
    }

    /**
     * Get the item's publishers.
     *
     * @return array
     * @access protected
     */
    protected function getPublishers()
    {
        $subfields = array('b');
        $fields = $this->getFieldArray('260', $subfields);
        return $fields;
    }

	/*
		Reprint date (260 $g)

		@access  protected
		@return  array
     */

	protected function getReprintDate()
	{
		$retVal = array();
		$imprints = $this->marcRecord->getFields('260');
		if ($imprints)
		{
			foreach ( $imprints as $imprint )
			{
				$reprintDate = $imprint->getSubfield('g');
				if ($reprintDate)
				{
					$retVal[] = $reprintDate->getData();
				}
			}
		}
		return $retVal;
    }

    protected function getExtendedMainUniformTitle()
    {
		$fields = array('130', '240');
		$retVal = array();
		foreach ($fields as $field)
		{
			$retVal = array_merge($retVal, $this->getCompleteFields($field, TRUE));
		}
		return $retVal;
    }

    protected function getExtendedCorporateAuthor()
    {
		return $this->getCompleteFields('110', TRUE);
    }

    protected function getExtendedConference()
    {
		return $this->getCompleteFields('111', TRUE);
    }

	protected function getExtendedPrimaryAuthor()
	{
		return $this->getCompleteFields('100', TRUE);
	}

	protected function getExtendedContributors()
	{
		return $this->getCompleteFields('700', TRUE);
	}

	protected function getExtendedAddedCorporateAuthor()
	{
		return $this->getCompleteFields('710', TRUE);
	}

	protected function getExtendedAddedConference()
	{
		return $this->getCompleteFields('711', TRUE);
	}

	protected function getExtendedPublication()
	{
		return $this->getCompleteFields('260', TRUE);
	}

	protected function getExtendedGenre()
	{
		return $this->getCompleteFields('655', TRUE);
	}

	protected function getExtendedWithNote()
	{
		return $this->getCompleteFields('501', TRUE);
	}

	protected function getExtendedScale()
	{
		return $this->getCompleteFields('507', TRUE);
	}

	protected function getAcquisitionSource()
	{
		return $this->getCompleteFields('037', TRUE);
	}	

	protected function getShelfReady()
	{
		return $this->getCompleteFields('595', TRUE);
	}		

	protected function getCompleteFields($field, $concat = FALSE)
	{
 		$retVal = array();
        $fields = $this->marcRecord->getFields($field);
		if ($fields)
		{
			foreach ($fields as $field)
			{
	            $data = array();
	            $subfields = $field->getSubfields();
	            foreach($subfields as $subfield)
	            {
	            	$data[] = $subfield->getData();
            	}

				$retVal[] = $concat ? preg_replace('/\s+/', ' ', trim(implode(' ', $data))) : $data;		
			}
		}

		return $retVal;
	}


    protected function getAccessionNumber()
    {
	/*
		Get the KENT accession number (015) from the marc21 

		@access  protected
		@return  array
     */

 		$retVal = array();
        $fields = $this->marcRecord->getFields('015');
		if ($fields)
		{
			foreach ($fields as $field)
			{
	            $data = '';
	            $subfields = $field->getSubfields();
	            foreach($subfields as $subfield)
	            {
	            	$data .= $subfield->getCode() == 'a' ? $subfield->getData() . ' ' : $subfield->getData() . ', ' ;
            	}
				$retVal[] = trim($data);
			}
		}
		return $retVal;
    }







/*  These are copied from MarcRecord because they are private functions and cannot
    be inherited
*/
    protected function getSeries()
    {
        $matches = array();
        
        // First check the 440, 800 and 830 fields for series information:
        $primaryFields = array(
            '440' => array('a', 'n', 'p'),
            '800' => array('a', 'b', 'c', 'd', 'e', 'f', 'i', 'n', 'p', 'q', 't'),
            '810' => array('a', 'b', 't'),
            '811' => array('a', 'n', 'd', 'c', 't'),
            '830' => array('a', 'n', 'p'));
        $matches = $this->getSeriesFromMARC($primaryFields);
        if (!empty($matches)) {
            return $matches;
        }

        // Now check 490 and display it only if 440/800/830 were empty:
        $secondaryFields = array('490' => array('a'));
        $matches = $this->getSeriesFromMARC($secondaryFields);
        if (!empty($matches)) {
            return $matches;
        }

        // Still no results found?  Resort to the Solr-based method just in case!
        return parent::getSeries();
    }

    /**
     * Support method for getSeries() -- given a field specification, look for
     * series information in the MARC record.
     *
     * @access  private
     * @param   $fieldInfo  array           Associative array of field => subfield
     *                                      information (used to find series name)
     * @return  array                       Series data (may be empty)
     */
    private function getSeriesFromMARC($fieldInfo) 
    {
        $matches = array();

        // Loop through the field specification....
        foreach($fieldInfo as $field => $subfields) {
            // Did we find any matching fields?
            $series = $this->marcRecord->getFields($field);
            if (is_array($series)) {
                foreach($series as $currentField) {
                    // Can we find a name using the specified subfield list?
                    $name = $this->getSubfieldArray($currentField, $subfields);
                    if (isset($name[0])) {
                        $currentArray = array('name' => $name[0]);

                        // Can we find a number in subfield v?  (Note that number is
                        // always in subfield v regardless of whether we are dealing
                        // with 440, 490, 800 or 830 -- hence the hard-coded array
                        // rather than another parameter in $fieldInfo).
                        $number = $this->getSubfieldArray($currentField, array('v'));
                        if (isset($number[0])) {
                            $currentArray['number'] = $number[0];
                        }

                        // Save the current match:
                        $matches[] = $currentArray;
                    }
                }
            }
        }

        return $matches;
    }

    /**
     * Return an array of non-empty subfield values found in the provided MARC
     * field.  If $concat is true, the array will contain either zero or one
     * entries (empty array if no subfields found, subfield values concatenated
     * together in specified order if found).  If concat is false, the array
     * will contain a separate entry for each subfield value found.
     *
     * @access  private
     * @param   object      $currentField   Result from File_MARC::getFields.
     * @param   array       $subfields      The MARC subfield codes to read
     * @param   bool        $concat         Should we concatenate subfields?
     * @return  array
     */
    private function getSubfieldArray($currentField, $subfields, $concat = true)
    {
        // Start building a line of text for the current field
        $matches = array();
        $currentLine = '';

        // Loop through all specified subfields, collecting results:
        foreach($subfields as $subfield) {
            $subfieldsResult = $currentField->getSubfields($subfield);
            if (is_array($subfieldsResult)) {
                foreach($subfieldsResult as $currentSubfield) {
                    // Grab the current subfield value and act on it if it is 
                    // non-empty:
                    $data = trim($currentSubfield->getData());
                    if (!empty($data)) {
                        // Are we concatenating fields or storing them separately?
                        if ($concat) {
                            $currentLine .= $data . ' ';
                        } else {
                            $matches[] = $data;
                        }
                    }
                }
            }
        }

        // If we're in concat mode and found data, it will be in $currentLine and
        // must be moved into the matches array.  If we're not in concat mode,
        // $currentLine will always be empty and this code will be ignored.
        if (!empty($currentLine)) {
            $matches[] = trim($currentLine);
        }

        // Send back our result array:
        return $matches;
    }

    /**
     * Return an array of all values extracted from the specified field/subfield
     * combination.  If multiple subfields are specified and $concat is true, they
     * will be concatenated together in the order listed -- each entry in the array
     * will correspond with a single MARC field.  If $concat is false, the return
     * array will contain separate entries for separate subfields.
     *
     * @param   string      $field          The MARC field number to read
     * @param   array       $subfields      The MARC subfield codes to read
     * @param   bool        $concat         Should we concatenate subfields?
     * @access  private
     * @return  array
     */
    private function getFieldArray($field, $subfields = null, $concat = true)
    {
        // Default to subfield a if nothing is specified.
        if (!is_array($subfields)) {
            $subfields = array('a');
        }

        // Initialize return array
        $matches = array();

        // Try to look up the specified field, return empty array if it doesn't exist.
        $fields = $this->marcRecord->getFields($field);
        if (!is_array($fields)) {
            return $matches;
        }

        // Extract all the requested subfields, if applicable.
        foreach($fields as $currentField) {
            $next = $this->getSubfieldArray($currentField, $subfields, $concat);
            $matches = array_merge($matches, $next);
        }

        return $matches;
    }

    /**
     * Get the first value matching the specified MARC field and subfields.
     * If multiple subfields are specified, they will be concatenated together.
     *
     * @param   string      $field          The MARC field to read
     * @param   array       $subfields      The MARC subfield codes to read
     * @access  private
     * @return  string
     */
    private function getFirstFieldValue($field, $subfields = null)
    {
        $matches = $this->getFieldArray($field, $subfields);
        return (is_array($matches) && count($matches) > 0) ?
            $matches[0] : null;
    }
}



?>