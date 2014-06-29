<?php
// Classes and libraries for module system
//
// webtrees: Web based Family History software
// Copyright (C) 2014 webtrees development team.
//
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA

if (!defined('WT_WEBTREES')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

class fancy_gendex_WT_Module extends WT_Module implements WT_Module_Config {
	
	public function __construct() {
		parent::__construct();
		// Load any local user translations
		if (is_dir(WT_MODULES_DIR.$this->getName().'/language')) {
			if (file_exists(WT_MODULES_DIR.$this->getName().'/language/'.WT_LOCALE.'.mo')) {
				WT_I18N::addTranslation(
					new Zend_Translate('gettext', WT_MODULES_DIR.$this->getName().'/language/'.WT_LOCALE.'.mo', WT_LOCALE)
				);
			}
			if (file_exists(WT_MODULES_DIR.$this->getName().'/language/'.WT_LOCALE.'.php')) {
				WT_I18N::addTranslation(
					new Zend_Translate('array', WT_MODULES_DIR.$this->getName().'/language/'.WT_LOCALE.'.php', WT_LOCALE)
				);
			}
			if (file_exists(WT_MODULES_DIR.$this->getName().'/language/'.WT_LOCALE.'.csv')) {
				WT_I18N::addTranslation(
					new Zend_Translate('csv', WT_MODULES_DIR.$this->getName().'/language/'.WT_LOCALE.'.csv', WT_LOCALE)
				);
			}
		}
	}

	// Extend WT_Module
	public function getTitle() {
		return /* I18N: Name of a module */ WT_I18N::translate('Fancy Gendex');
	}

	// Extend WT_Module
	public function getDescription() {
		return /* I18N: Description of the module */ WT_I18N::translate('Generate GENDEX file for genealogical search engines.');
	}	
	
	// Get a list of all the individuals for the choosen gedcom
	private function getAllNames($tree_id) {
		$sql = "SELECT SQL_CACHE n_id, n_surname, n_givn FROM `##name` WHERE n_file=? AND n_type=? ORDER BY n_sort ASC";
		$args = array($tree_id, 'NAME');

		foreach (WT_DB::prepare($sql)->execute($args)->fetchAll() as $row) {
			$list[] = array(
				'ID' 		=>$row->n_id,
				'SURNAME' 	=>strtoupper($row->n_surname),
				'GIVN' 		=>$row->n_givn
			);
		}
		return $list;
	}
	
	private function get_gendex_content($tree, $indis) {
		$content = '';
		foreach($indis as $indi) {
			$xref = $indi['ID'];
			$record = WT_Individual::getInstance($xref, $tree->tree_id);
			if($record && $record->canShowName(WT_PRIV_PUBLIC)) {
				$content.=$record->getXref().'|'.$indi['SURNAME'].'|'.$indi['GIVN'].' /'.$indi['SURNAME'].'/|'.$this->print_date('BIRT', $xref).'|'.$record->getBirthPlace().'|'.$this->print_date('DEAT', $xref).'|'.$record->getDeathPlace().'|'.PHP_EOL;
			}
		}
		return $content;
	}
	
	private function print_date($fact, $xref) {
		$row=
			WT_DB::prepare("SELECT SQL_CACHE d_year, d_month, d_day FROM `##dates` WHERE d_fact=? AND d_gid=? LIMIT 1")
			->execute(array($fact, $xref))
			->fetchOneRow();
		if ($row) {
			$day = $row->d_day > 0 ? $row->d_day.' ' : ''; 
			$month = !empty($row->d_month) ? $row->d_month.' ' : '';
			$year = $row->d_year > 0 ? $row->d_year : ''; 
			$date=$day.$month.$year;
			return $date;
		} else {
			return '';
		}
	}
	
	// Extend WT_Module
	public function modAction($mod_action) {
		switch($mod_action) {
		case 'admin_config':
			$this->config();
			break;
		default:
			header('HTTP/1.0 404 Not Found');
		}
	}
	
	private function config() {
		$controller=new WT_Controller_Page();
		$controller
			->requireAdminLogin()
			->setPageTitle($this->getTitle())
			->pageHeader();

		// Save the updated preferences
		if (WT_Filter::post('action')=='save' && WT_Filter::checkCsrf()) {
			foreach (WT_Tree::getAll() as $tree) {
				set_gedcom_setting($tree->tree_id, 'FANCY_GENDEX', WT_Filter::postBool('FG'.$tree->tree_id));
			}	
			$this->create_gendex();
		}

		$html =   '<h2>'.$this->getTitle().'</h2>'
				. '<p>'.WT_I18N::translate('A GENDEX file is an index of personal data and a short page URL. It is used to index a genealogical website by a genealogical search engine. The idea behind it is to join numerous pedigrees of individual genealogical researchers to a central database, while the individual genealogical researchers are still keeping  all control over their data. Unlike a GEDCOM file a GENDEX file contains no information about the family relationships between individuals. This means the file is useless without the corresponding website.').'</p>'
				. '<i>'.WT_I18N::translate('The GENDEX file will only contain public data.').'</i><hr>'
				. '<form method="post" action="module.php?mod=' . $this->getName() . '&amp;mod_action=admin_config">'
				. '<input type="hidden" name="action" value="save">'.WT_Filter::getCsrf()
				. '<h3>'.WT_I18N::translate('Which family trees should be included in the GENDEX file?').'</h3>';
				foreach (WT_Tree::getAll() as $tree) {
					$html .= '<p><input type="checkbox" name="FG'.$tree->tree_id.'"';
					if (get_gedcom_setting($tree->tree_id, 'FANCY_GENDEX')) {
						$html .= ' checked="checked"';
					}
					$html .= '>'.$tree->tree_title_html.'</p>';
				}
		
		if(file_exists(WT_ROOT . 'gendex.txt')) {
			$button_text = WT_I18N::translate('update GENDEX text file');
		}
		else {
			$button_text = WT_I18N::translate('create GENDEX text file');			
		}
		
		$html .=  '<input type="submit" value="'.$button_text.'">'
				. '</form><hr>';
		
		$gendex_url	= WT_SERVER_NAME.WT_SCRIPT_PATH.'gendex.txt';
		
		if(file_exists(WT_ROOT . 'gendex.txt')) {		
			$html .=  '<p>'.WT_I18N::translate('Click on the link below to view your GENDEX file.').'</p>'
					. '<a href="'.$gendex_url.'">'.$gendex_url.'</a>'
					. '<p>'.WT_I18N::translate('To tell search engines that a GENDEX file is available, you should submit the url above to the genealogical search engine of your choice. Currently only <a href="http://www.stamboomzoeker.nl">stamboomzoeker.nl</a> (Dutch) and <a href="http://www.familytreeseeker.com">familytreeseeker.com</a> (English/International) are supported.').'</p>'
					. '<p>'.WT_I18N::translate('Use this url (without the quotes) as general url to the individual pages:'). ' “'.WT_SERVER_NAME.WT_SCRIPT_PATH.'individual.php?pid=”'
					. '</p><hr>'
					. '<p>'.WT_I18N::translate('Note: the GENDEX text file is not automatically updated. If you have made changes to your tree you need to update the GENDEX text file manually by clicking on the button. You do <b>not</b> need to update your subscription at the genealogical search engine.')
					. '</p><hr>';
		}		
		echo $html;
	}

	// Implement WT_Module_Config
	public function getConfigLink() {
		return 'module.php?mod='.$this->getName().'&amp;mod_action=admin_config';
	}

	// The GENDEX file contains references to all none private individuals.
	private function create_gendex() {
		$data=';;Generated with '.WT_WEBTREES.' '.WT_VERSION.' on '.strip_tags(format_timestamp(WT_CLIENT_TIMESTAMP)).PHP_EOL;
		foreach (WT_Tree::getAll() as $tree) {
			if(get_gedcom_setting($tree->tree_id, 'FANCY_GENDEX')) {
				$data .= $this->get_gendex_content($tree, $this->getAllNames($tree->tree_id));
			}
		}
		// create GENDEX text file
		Zend_Session::writeClose();
		$filename = WT_ROOT . 'gendex.txt';
		
		// make our GENDEX text file if it does not exist.
		if(!file_exists($filename)) {
			$handle = @fopen($filename, 'w');
			fclose($handle);
			chmod($filename, WT_PERM_FILE);
		}
		
		// Let's make sure the file exists and is writable first.
		if (is_writable($filename)) {
		
			if (!$handle = @fopen($filename, 'w')) {
				 exit;
			}
		
			// Write the GENDEX data to our gendex.txt file.
			if (fwrite($handle, $data) === FALSE) {
				exit;
			}		
			fclose($handle);			
		}
	}	
}
