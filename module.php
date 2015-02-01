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

use WT\Auth;

class fancy_gendex_WT_Module extends WT_Module implements WT_Module_Config {

	public function __construct() {
		parent::__construct();
		// Load any local user translations
		if (is_dir(WT_MODULES_DIR . $this->getName() . '/language')) {
			if (file_exists(WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.mo')) {
				WT_I18N::addTranslation(
					new Zend_Translate('gettext', WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.mo', WT_LOCALE)
				);
			}
			if (file_exists(WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.php')) {
				WT_I18N::addTranslation(
					new Zend_Translate('array', WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.php', WT_LOCALE)
				);
			}
			if (file_exists(WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.csv')) {
				WT_I18N::addTranslation(
					new Zend_Translate('csv', WT_MODULES_DIR . $this->getName() . '/language/' . WT_LOCALE . '.csv', WT_LOCALE)
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
		$filter = new Zend_Filter_StringToUpper(array('encoding' => 'UTF-8'));

		foreach (WT_DB::prepare($sql)->execute($args)->fetchAll() as $row) {
			$list[] = array(
				'ID'		 => $row->n_id,
				'SURNAME'	 => $filter->filter($row->n_surname),
				'GIVN'		 => $row->n_givn
			);
		}
		return $list;
	}

	private function get_gendex_content($tree, $indis) {
		$content = '';
		foreach ($indis as $indi) {
			$xref = $indi['ID'];
			$record = WT_Individual::getInstance($xref, $tree->id());
			if ($record && $record->canShowName(WT_PRIV_PUBLIC)) {
				$content.=$record->getXref() . '&ged=' . $tree->name() . '|' . $indi['SURNAME'] . '|' . $indi['GIVN'] . ' /' . $indi['SURNAME'] . '/|' . $this->print_date('BIRT', $xref) . '|' . $record->getBirthPlace() . '|' . $this->print_date('DEAT', $xref) . '|' . $record->getDeathPlace() . '|' . PHP_EOL;
			}
		}
		return $content;
	}

	private function print_date($fact, $xref) {
		$row = WT_DB::prepare("SELECT SQL_CACHE d_year, d_month, d_day FROM `##dates` WHERE d_fact=? AND d_gid=? LIMIT 1")
			->execute(array($fact, $xref))
			->fetchOneRow();
		if ($row) {
			$day = $row->d_day > 0 ? $row->d_day . ' ' : '';
			$month = !empty($row->d_month) ? $row->d_month . ' ' : '';
			$year = $row->d_year > 0 ? $row->d_year : '';
			$date = $day . $month . $year;
			return $date;
		} else {
			return '';
		}
	}

	// Extend WT_Module
	public function modAction($mod_action) {
		switch ($mod_action) {
			case 'admin_config':
				$this->config();
				break;
			default:
				header('HTTP/1.0 404 Not Found');
		}
	}

	private function config() {
		$controller = new WT_Controller_Page();
		$controller
			->restrictAccess(Auth::isAdmin())
			->setPageTitle($this->getTitle())
			->pageHeader();

		// Save the updated preferences
		if (WT_Filter::post('action') == 'save' && WT_Filter::checkCsrf()) {
			foreach (WT_Tree::getAll() as $tree) {
				$tree->setPreference('FANCY_GENDEX', WT_Filter::postBool('FG' . $tree->id()));
			}
			$this->create_gendex();
		}
		?>
		
		<ol class="breadcrumb small">
			<li><a href="admin.php"><?php echo WT_I18N::translate('Control panel'); ?></a></li>
			<li><a href="admin_modules.php"><?php echo WT_I18N::translate('Module administration'); ?></a></li>
			<li class="active"><?php echo $controller->getPageTitle(); ?></li>
		</ol>
		<h2><?php echo $controller->getPageTitle(); ?></h2>
		<p><?php echo WT_I18N::translate('A GENDEX file is an index of personal data and a short page URL. It is used to index a genealogical website by a genealogical search engine. The idea behind it is to join numerous pedigrees of individual genealogical researchers to a central database, while the individual genealogical researchers are still keeping  all control over their data. Unlike a GEDCOM file a GENDEX file contains no information about the family relationships between individuals. This means the file is useless without the corresponding website.'); ?></p>
		<p><?php echo WT_I18N::translate('The GENDEX file will only contain public data.'); ?></p>
		<hr style="border-color:#ccc">
		<form method="post" action="module.php?mod=<?php echo $this->getName(); ?>&amp;mod_action=admin_config">
			<input type="hidden" name="action" value="save"><?php echo WT_Filter::getCsrf(); ?>
			<h4><?php echo WT_I18N::translate('Which family trees should be included in the GENDEX file?'); ?></h4>
			<div class="form-group">
				<?php foreach (WT_Tree::getAll() as $tree): ?>
					<div class="checkbox">
						<label>
							<input
								type="checkbox"
								name="FG<?php echo $tree->id(); ?>"
								<?php if ($tree->getPreference('FANCY_GENDEX')): ?>
									checked="checked"
								<?php endif; ?>
								>
								<?php echo $tree->nameHtml(); ?>
						</label>
					</div>
				<?php endforeach; ?>
			</div>
			<?php
			if (file_exists(WT_ROOT . 'gendex.txt')) {
				$button_text = WT_I18N::translate('update GENDEX text file');
			} else {
				$button_text = WT_I18N::translate('create GENDEX text file');
			}
			?>
			<button type="submit" class="btn btn-primary">
				<?php echo $button_text; ?>
			</button>
		</form>
		<hr style="border-color:#ccc">
		<?php $gendex_url = WT_BASE_URL . 'gendex.txt'; ?>
		<?php if (file_exists(WT_ROOT . 'gendex.txt')): ?>
			<p><?php echo WT_I18N::translate('Click on the link below to view your GENDEX file.'); ?></p>
			<p><a href="<?php echo $gendex_url; ?>" target="_blank"><?php echo $gendex_url; ?></a></p>
			<p><?php echo WT_I18N::translate('To tell search engines that a GENDEX file is available, you should submit the url above to the genealogical search engine of your choice.'); ?></p>
			<p><?php echo WT_I18N::translate('The search engines below are known to accept the GENDEX textfile created by this module:'); ?></p>
			<ul>
				<li>
					<a href="http://www.gendexnetwork.org" target="_blank">The Gendex Network</a> (<?php echo WT_I18N::translate('English/International'); ?>)
				</li>
				<li>
					<a href="http://www.familytreeseeker.com/?l=en" target="_blank">Familytreeseeker</a> (<?php echo WT_I18N::translate('English/International'); ?>)
				</li>
				<li>
					<a href="http://www.stamboomzoeker.nl/?l=nl" target="_blank">Stamboomzoeker</a> (<?php echo WT_I18N::translate('Dutch'); ?>)
				</li>
			</ul>
			<p><?php echo WT_I18N::translate('You need this url (without the quotes) as general url to the individual pages when submitting to Stamboomzoeker or Familytreeseeker:'); ?> “<?php echo WT_BASE_URL; ?>individual.php?pid=”</p>
			<p class="alert alert-info"><?php echo WT_I18N::translate('Note: the GENDEX text file is not automatically updated. If you have made changes to your tree you need to update the GENDEX text file manually by clicking on the button. You do <b>not</b> need to update your subscription at the genealogical search engine.'); ?></p>
			<hr>
		<?php endif; ?>
		<?php
	}

	// Implement WT_Module_Config
	public function getConfigLink() {
		return 'module.php?mod=' . $this->getName() . '&amp;mod_action=admin_config';
	}

	// The GENDEX file contains references to all none private individuals.
	private function create_gendex() {
		$data = ';;Generated with ' . WT_WEBTREES . ' ' . WT_VERSION . ' on ' . strip_tags(format_timestamp(WT_CLIENT_TIMESTAMP)) . PHP_EOL;
		foreach (WT_Tree::getAll() as $tree) {
			if ($tree->getPreference('FANCY_GENDEX')) {
				$data .= $this->get_gendex_content($tree, $this->getAllNames($tree->id()));
			}
		}

		//  add UTF-8 byte-order mark to the data - see: http://stackoverflow.com/a/12215021
		$data = "\xEF\xBB\xBF" . $data;

		// create GENDEX text file
		Zend_Session::writeClose();
		$filename = WT_ROOT . 'gendex.txt';

		// make our GENDEX text file if it does not exist.
		if (!file_exists($filename)) {
			$handle = @fopen($filename, 'w');
			fclose($handle);
			chmod($filename, 0644);
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
