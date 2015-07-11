<?php
/*
 * webtrees: online genealogy
 * Copyright (C) 2015 webtrees development team
 * Copyright (C) 2015 JustCarmen
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
namespace JustCarmen\WebtreesAddOns\FancyGendex;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Functions\FunctionsDate;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Tree;
use Zend_Filter_StringToUpper;

/**
 * Class Fancy Gendex
 */
class FancyGendexClass extends FancyGendexModule {

	// The GENDEX file contains references to all none private individuals.
	protected function createGendex() {
		$data = ';;Generated with ' . WT_WEBTREES . ' ' . WT_VERSION . ' on ' . strip_tags(FunctionsDate::formatTimestamp(WT_TIMESTAMP + WT_TIMESTAMP_OFFSET)) . PHP_EOL;
		foreach (Tree::getAll() as $tree) {
			if ($tree->getPreference('FANCY_GENDEX')) {
				$data .= $this->getGendexContent($tree, $this->getAllNames($tree->getTreeId()));
			}
		}

		// create GENDEX text file
		$file = WT_ROOT . 'gendex.txt';

		// make our GENDEX text file if it does not exist.
		if (!file_exists($file)) {
			if (!$handle = fopen($file, 'w')) {
				echo $this->addMessage(I18N::translate('The GENDEX file can not be created automatically. Try to manually create an empty text file in the root of your webtrees installation, called “gendex.txt”. Set the file permissions to 644.'), 'danger');
			} else {
				$this->writeGendexFile($handle, $data);
				chmod($file, 0644);
				echo $this->addMessage(I18N::translate('The GENDEX file has been created.'), 'success');
			}
		} else {
			if (!$handle = fopen($file, 'w')) {
				echo $this->addMessage(I18N::translate('Writing to the GENDEX file failed. Be sure you have set the right file permissions (644).'), 'danger');
			} else {
				$this->writeGendexFile($handle, $data);
				echo $this->addMessage(I18N::translate('The GENDEX file has been updated.'), 'success');
			}
		}
	}

	// Get a list of all the individuals for the choosen gedcom
	private function getAllNames($tree_id) {
		$sql = "SELECT SQL_CACHE n_id, n_surname, n_givn FROM `##name` WHERE n_file = :tree_id AND n_type = 'NAME' ORDER BY n_sort ASC";
		$args = array(
			'tree_id' => $tree_id
		);
		$filter = new Zend_Filter_StringToUpper(array('encoding' => 'UTF-8'));

		foreach (Database::prepare($sql)->execute($args)->fetchAll() as $row) {
			$list[] = array(
				'ID'		 => $row->n_id,
				'SURNAME'	 => $filter->filter($row->n_surname),
				'GIVN'		 => $row->n_givn
			);
		}
		return $list;
	}

	private function getGendexContent($tree, $indis) {
		$content = '';
		foreach ($indis as $indi) {
			$xref = $indi['ID'];
			$record = Individual::getInstance($xref, $tree);
			if ($record && $record->canShowName(Auth::PRIV_PRIVATE)) {
				$content.=$record->getXref() . '&ged=' . $tree->getName() . '|' . $indi['SURNAME'] . '|' . $indi['GIVN'] . ' /' . $indi['SURNAME'] . '/|' . $this->printDate(array('BIRT', 'BAPM', 'CHR'), $xref, $tree) . '|' . $record->getBirthPlace() . '|' . $this->printDate(array('DEAT', 'BURI'), $xref, $tree) . '|' . $record->getDeathPlace() . '|' . PHP_EOL;
			}
		}
		return $content;
	}

	private function printDate($facts, $xref, $tree) {
		foreach ($facts as $fact) {
			$row = Database::prepare(
					"SELECT SQL_CACHE d_year, d_month, d_day FROM `##dates`" .
					" WHERE d_fact = :fact" .
					" AND d_gid = :xref" .
					" AND d_file = :tree_id" .
					" LIMIT 1"
				)
				->execute(array(
					'fact'		 => $fact,
					'xref'		 => $xref,
					'tree_id'	 => $tree->getTreeId()))
				->fetchOneRow();
			if ($row) {
				$day = $row->d_day > 0 ? $row->d_day . ' ' : '';
				$month = !empty($row->d_month) ? $row->d_month . ' ' : '';
				$year = $row->d_year > 0 ? $row->d_year : '';
				$date = $day . $month . $year;
				return $date;
			}
		}
	}

	private function writeGendexFile($handle, $data) {
		#UTF-8 - Add byte order mark
		fwrite($handle, pack('CCC', 0xef, 0xbb, 0xbf));
		fwrite($handle, $data);
		fclose($handle);
	}

	private function addMessage($message, $type) {
		return
			'<div class="alert alert-' . $type . ' alert-dismissible" role="alert">' .
			'<button type="button" class="close" data-dismiss="alert" aria-label="' . I18N::translate('close') . '">' .
			'<span aria-hidden="true">&times;</span>' .
			'</button>' .
			'<span class="message">' . $message . '</span>' .
			'</div>';
	}

}
