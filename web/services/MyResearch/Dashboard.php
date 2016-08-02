<?php
/**
 * Dashboard action for MyResearch module
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
 * @package  Controller_MyResearch
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
require_once 'services/MyResearch/MyResearch.php';

/**
 * Dashboard action for MyResearch module
 *
 * @category VuFind
 * @package  Controller_MyResearch
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_module Wiki
 */
class Dashboard extends MyResearch
{
    /**
     * Process parameters and display the page.
     *
     * @return void
     * @access public
     */
    public function launch()
    {
        global $interface;
        global $user;

        $interface->setTemplate('dashboard.tpl');
        $interface->setPageTitle('Account Dashboard');


        // Get My Transactions
        if ($patron = UserAccount::catalogLogin()) {
            if (PEAR::isError($patron)) {
                PEAR::raiseError($patron);
            }

            $libs = $this->catalog->getPickUpLocations($patron);
            $defaultPickUpLocation = $this->catalog->getDefaultPickUpLocation($patron);
            $interface->assign('pickup', $libs);
            $interface->assign('defaultPickup', $defaultPickUpLocation);

            // Renew Items
            if (isset($_REQUEST['renewAll']) || isset($_REQUEST['renewSelected'])) {
                $renewResult = $this->_renewItems($patron);
            }

            $result = $this->catalog->getMyTransactions($patron);
            if (PEAR::isError($result)) {
                PEAR::raiseError($result);
            }

            $transList = array();
            foreach ($result as $data) {
                $current = array('ils_details' => $data);
                if ( isset($current['ils_details']['duedate']) ) {
                    $dateStr = str_replace(array( '(', ')' ), '',$current['ils_details']['duedate']);
                    $duedate = strtotime($dateStr);
                } else {
                    $duedate = NULL;
                }

		  //Work out what to suprress from template
		  $temp_status = isset($current['ils_details']['status']) ? $current['ils_details']['status'] : null;
		  $temp_status = (substr_count(trim(strtolower($temp_status)), "recall request") > 0) ? "Recalled" : $temp_status;
		  $temp_status = ((substr_count(trim(strtolower($temp_status)), "overdue") < 0) || (substr_count(trim(strtolower($temp_status)), "charged") > 0) || (substr_count(trim(strtolower($temp_status)), "renewed") > 0)) ? "" : $temp_status;


                $current += array(
                    'item_id' => isset($current['ils_details']['item_id']) ? $current['ils_details']['item_id'] : null,
                    'duedate' => $duedate,
                    'status' => $temp_status,
                    'callnumber' => isset($current['ils_details']['callNumber']) ? $current['ils_details']['callNumber'] : null,
                    'itemType' => isset($current['ils_details']['itemType']) ? $current['ils_details']['itemType'] : null,
                    'location' => isset($current['ils_details']['location']) ? $current['ils_details']['location'] : null,
                    'messages' => isset($current['ils_details']['messages']) ? $current['ils_details']['messages'] : null,
                );

                if ($record = $this->db->getRecord($data['id'])) {
                    $current += array(
                        'id' => $data['id'],
                        'isbn' => isset($record['isbn']) ? $record['isbn'] : null,
                        'author' => isset($record['author']) ? $record['author'] : null,
                        'title' => isset($record['title']) ? $record['title'] : null,
                        'format' => isset($record['format']) ? $record['format'] : null,
                    );
                }
                if ($current['duedate'])
                {
					$secondsInDay = (60*60*24);
					$secondsToday = time()%$secondsInDay;
					
					$current['dueIn'] = $current['duedate']-(time());
					$current['dueInDays'] = $current['dueIn']/(60*60*24);
					
					if ($current['dueIn']<0) {
						$current['dueText'] = "overdue";
						$current['urgancy']=2;
					} else if (date("Y-m-d",$current['duedate']) == date("Y-m-d",time())) {
						$current['dueText'] = "due";
						$current['dueInText'] = "today";
						$current['urgancy']=1;
					} elseif (floor($current['dueInDays'])<1) {
						$current['dueText'] = "due";
						$current['dueInText'] = "tomorrow";
						$current['urgancy']=1;
					} elseif ($current['dueInDays']<7) {
						$current['dueText'] = "due in";
						$days = floor($current['dueInDays']);
						$current['dueInText'] = $days." day";
						if ($days>1) {
							$current['dueInText'] .= "s";	
						}
					} elseif ($current['dueInDays']<31) {
						$current['dueText'] = "due in";
						$weeks = floor($current['dueInDays']/7);
						$current['dueInText'] = $weeks." week";
						if ($weeks>1) {
							$current['dueInText'] .= "s";
						}
					} else {
						$current['dueText'] = "due in";
						$months = floor($current['dueInDays']/30);
						$current['dueInText'] = $months." month";
						if ($months > 1) {
							$current['dueInText'] .= "s";
						}
					}
				}				
                $transList[] = $current;
            }

            if ($this->checkRenew) {
                $transList = $this->_addRenewDetails($transList);
            }


        }
        $interface->assign('transList', $transList);
        $interface->assign('transLeft', count($transList)-5);

        // Fines
        $result = $this->catalog->getMyFines($patron);
        if (!PEAR::isError($result)) {
            if (count($result)) {
                // Drop the index object into a global
                //  so it's accessible inside the callback.
//                 $finesIndexEngine = $this->db;
// 
//                    $record = $this->db->getRecord($data['bibid']);
                
                $total = 0;
                foreach($result as &$res) {
                    $total  += $res['balance'];
                }
//                 $fines = '';
//                 foreach($result as &$res) {
//                     $fines[$res['fineFeeId']][] = &$res;
//                }
                $interface->assign("numberOfFines",count($result));
                $interface->assign("total",$total);
            } else {
                $interface->assign("numberOfFines",0);
                $interface->assign("total",0);
            }
        }

        // reservations
        $result = $this->catalog->getMyHolds($patron);
        if (!PEAR::isError($result)) {
            if (count($result)) {
                $recordList = array();
                $availableList = array();
                foreach ($result as $row) {
                    $record = $this->db->getRecord($row['id']);
                    $record['ils_details'] = $row;
                    $record['ils_details']['available']
                      ? $availableList[] = $record
                      : $recordList[] = $record;
                }

                // Get List of PickUp Libraries based on patrons home library
                $interface->assign('requestList', $recordList);
                $interface->assign('availableList', $availableList);
            } else {
                 $interface->assign('requestList', false);
            }
         }

        // saved searches
        $s = new SearchEntry();
        $searchHistory = $s->getSearches(session_id(), is_object($user) ? $user->id : null);

        if (count($searchHistory) > 0) {
            // Build an array of history entries
            $history = array();
            $saved = array();
            // Loop through the history
            foreach($searchHistory as $search) {
                $size = strlen($search->search_object);
                $minSO = unserialize($search->search_object);
                $searchObject = SearchObjectFactory::deminify($minSO);
                
                // Make sure all facets are active so we get appropriate
                // descriptions in the filter box.
                $searchObject->activateAllFacets();

                $newItem = array(
                    'time'        => date("g:ia, jS M y", $searchObject->getStartTime()),
                    'url'         => $searchObject->renderSearchUrl(),
                    'searchId'    => $searchObject->getSearchId(),
                    'description' => $searchObject->displayQuery(),
                    'filters'     => $searchObject->getFilterList(),
                    'hits'        => number_format($searchObject->getResultTotal()),
                    'speed'       => round($searchObject->getQuerySpeed(), 2)."s",
                    // Size is purely for debugging. Not currently displayed in the template.
                    // It's the size of the serialized, minified search in the database.
                    'size'        => round($size/1024, 3)."kb"
                );

                // Saved searches
                if ($search->saved == 1) {
                    $saved[] = $newItem;
                } else {
                    $history[] = $newItem;
                }
            }

            // One final check, after a purge make sure we still have a history
            if (count($saved) > 0) {
                $interface->assign('saved', array_slice(array_reverse($saved),0,3));
                $interface->assign('noSavedSearches', false);
                if (count($saved)>3) {
	                $interface->assign('savedSearchesLeft', count($saved)-3);	
                } else {
	                $interface->assign('savedSearchesLeft', false);	                
                }
            // Nothing left in history
            } else {
                $interface->assign('noSavedSearches', true);
            }
            
            // One final check, after a purge make sure we still have a history
            if (count($history) > 0) {
                $interface->assign('history', array_slice(array_reverse($history),0,3));
                $interface->assign('noHistorySearches', false);
                if (count($history)>3) {
	                $interface->assign('historySearchesLeft', count($history)-3);	
                } else {
	                $interface->assign('historySearchesLeft', false);	                
                }
            // Nothing left in history
            } else {
                $interface->assign('noHistorySearches', true);
            }
            
        // No history
        } else {
            $interface->assign('noSavedSearches', true);
            $interface->assign('noHistorySearches', false);
        }
        
        // Get My Tags
        $tagList = $user->getTags();
        $interface->assign('tagList', $tagList);
        if (count($tagList)>21) {
	        $interface->assign('tagListLeft', count($tagList)-21);
        } else {
            $interface->assign('tagListLeft', 0);
        }        

        // Get My Lists
        $listList = $user->getLists();
        $interface->assign('listList', array_slice($listList,0,7));
        if (count($listList)>7) {
	        $interface->assign('listListLeft', count($listList)-7);
        } else {
            $interface->assign('listListLeft', 0);
        }

        // Get My Profile
        $profile = $this->catalog->getMyProfile($patron);
        if (!PEAR::isError($profile)) {
                $profile['home_library'] = $user->home_library;
                $interface->assign('profile', $profile);
        }

        $interface->display('layout.tpl');
    }

    /**
     * Adds a link or form details to existing checkout details
     *
     * @param array $transList An array of patron items
     *
     * @return array An array of patron items with links / form details
     * @access private
     */
    private function _addRenewDetails($transList)
    {
        global $interface;
        $session_details = array();

        foreach ($transList as $key => $item) {
            if ($this->checkRenew['function'] == "renewMyItemsLink") {
                // Build OPAC URL
                $transList[$key]['ils_details']['renew_link']
                    = $this->catalog->renewMyItemsLink($item['ils_details']);
            } else {
                // Form Details
                if ($transList[$key]['ils_details']['renewable']) {
                    $interface->assign('renewForm', true);
                }
                $renew_details
                    = $this->catalog->getRenewDetails($item['ils_details']);
                $transList[$key]['ils_details']['renew_details']
                    = $session_details[] = $renew_details;
            }
        }

        // Save all valid options in the session so user input can be validated later
        $_SESSION['renewValidData'] = $session_details;
        return $transList;
    }

    /**
     * Private method for renewing items
     *
     * @param array $patron An array of patron information
     *
     * @return boolean true on success, false on failure
     * @access private
     */
    private function _renewItems($patron)
    {
        global $interface;

        $gatheredDetails['details'] = isset($_REQUEST['renewAll'])
            ? $_REQUEST['renewAllIDS'] : $_REQUEST['renewSelectedIDS'];

        if (is_array($gatheredDetails['details'])) {
            $session_details = $_SESSION['renewValidData'];
            foreach ($gatheredDetails['details'] as $info) {
                // If the user input contains a value not found in the session
                // whitelist, something has been tampered with -- abort the process.
                if (!in_array($info, $session_details)) {
                    $interface->assign('errorMsg', 'error_inconsistent_parameters');
                    return false;
                }
            }

            // Add Patron Data to Submitted Data
            $gatheredDetails['patron'] = $patron;
            $renewResult = $this->catalog->renewMyItems($gatheredDetails);

            if ($renewResult !== false) {
                // Assign Blocks to the Template
                $interface->assign('blocks', $renewResult['block']);

                // Assign Results to the Template
                $interface->assign('renewResult', $renewResult['details']);

                return true;

            } else {
                 $interface->assign('errorMsg', 'renew_system_error');
            }
        } else {
            $interface->assign('errorMsg', 'renew_empty_selection');
        }
        return false;
    }
}

?>