<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2015 webtrees development team
 * Copyright (C) 2015 JustCarmen
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace Fisharebest\Webtrees;

use Fisharebest\Webtrees\Controller\PageController;
use Fisharebest\Webtrees\Functions\FunctionsDate;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Zend_Filter_StringToUpper;

class FancyGendexModule extends AbstractModule implements ModuleConfigInterface {

	public function __construct() {
		parent::__construct('fancy_gendex');
	}

	// Extend Module
	public function getTitle() {
		return /* I18N: Name of a module */ I18N::translate('Fancy Gendex');
	}

	// Extend Module
	public function getDescription() {
		return /* I18N: Description of the module */ I18N::translate('Generate GENDEX file for genealogical search engines.');
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

	// Extend Module
	public function modAction($mod_action) {
		switch ($mod_action) {
			case 'admin_config':
				$this->config();
				break;
			default:
				http_response_code(404);
				break;
		}
	}

	private function config() {
		$controller = new PageController();
		$controller
			->restrictAccess(Auth::isAdmin())
			->setPageTitle($this->getTitle())
			->pageHeader();

		// Save the updated preferences
		if (Filter::post('action') == 'save' && Filter::checkCsrf()) {
			foreach (Tree::getAll() as $tree) {
				$tree->setPreference('FANCY_GENDEX', Filter::postBool('FG' . $tree->getTreeId()));
			}
			$this->createGendex();
		}
		?>

		<ol class="breadcrumb small">
			<li><a href="admin.php"><?php echo I18N::translate('Control panel'); ?></a></li>
			<li><a href="admin_modules.php"><?php echo I18N::translate('Module administration'); ?></a></li>
			<li class="active"><?php echo $controller->getPageTitle(); ?></li>
		</ol>
		<h2><?php echo $controller->getPageTitle(); ?></h2>
		<p><?php echo I18N::translate('A GENDEX file is an index of personal data and a short page URL. It is used to index a genealogical website by a genealogical search engine. The idea behind it is to join numerous pedigrees of individual genealogical researchers to a central database, while the individual genealogical researchers are still keeping  all control over their data. Unlike a GEDCOM file a GENDEX file contains no information about the family relationships between individuals. This means the file is useless without the corresponding website.'); ?></p>
		<p><?php echo I18N::translate('The GENDEX file will only contain public data.'); ?></p>
		<hr style="border-color:#ccc">
		<form method="post" action="module.php?mod=<?php echo $this->getName(); ?>&amp;mod_action=admin_config">
			<input type="hidden" name="action" value="save"><?php echo Filter::getCsrf(); ?>
			<h4><?php echo I18N::translate('Which family trees should be included in the GENDEX file?'); ?></h4>
			<div class="form-group">
		<?php foreach (Tree::getAll() as $tree): ?>
					<div class="checkbox">
						<label>
							<input
								type="checkbox"
								name="FG<?php echo $tree->getTreeId(); ?>"
			<?php if ($tree->getPreference('FANCY_GENDEX')): ?>
									checked="checked"
								<?php endif; ?>
								>
								<?php echo $tree->getTitleHtml(); ?>
						</label>
					</div>
		<?php endforeach; ?>
			</div>
				<?php
				if (file_exists(WT_ROOT . 'gendex.txt')) {
					$button_text = I18N::translate('update GENDEX text file');
				} else {
					$button_text = I18N::translate('create GENDEX text file');
				}
				?>
			<button type="submit" class="btn btn-primary">
				<i class="fa fa-check"></i>
		<?php echo $button_text; ?>
			</button>
		</form>
		<hr style="border-color:#ccc">
		<?php $gendex_url = WT_BASE_URL . 'gendex.txt'; ?>
		<?php if (file_exists(WT_ROOT . 'gendex.txt')): ?>
			<p><?php echo I18N::translate('Click on the link below to view your GENDEX file.'); ?></p>
			<p><a href="<?php echo $gendex_url; ?>" target="_blank"><?php echo $gendex_url; ?></a></p>
			<p><?php echo I18N::translate('To tell search engines that a GENDEX file is available, you should submit the url above to the genealogical search engine of your choice.'); ?></p>
			<p><?php echo I18N::translate('The search engines below are known to accept the GENDEX textfile created by this module:'); ?></p>
			<ul>
				<li>
					<a href="http://www.gendexnetwork.org" target="_blank">The Gendex Network</a> (<?php echo I18N::translate('English/International'); ?>)
				</li>
				<li>
					<a href="http://www.familytreeseeker.com/?l=en" target="_blank">Familytreeseeker</a> (<?php echo I18N::translate('English/International'); ?>)
				</li>
				<li>
					<a href="http://www.stamboomzoeker.nl/?l=nl" target="_blank">Stamboomzoeker</a> (<?php echo I18N::translate('Dutch'); ?>)
				</li>
			</ul>
			<p><?php echo I18N::translate('You need this url (without the quotes) as general url to the individual pages when submitting to Stamboomzoeker or Familytreeseeker:'); ?> “<?php echo WT_BASE_URL; ?>individual.php?pid=”</p>
			<p class="alert alert-info"><?php echo I18N::translate('Note: the GENDEX text file is not automatically updated. If you have made changes to your tree you need to update the GENDEX text file manually by clicking on the button. You do <b>not</b> need to update your subscription at the genealogical search engine.'); ?></p>
			<hr>
		<?php endif; ?>
		<?php
	}

	// Implement ModuleConfigInterface
	public function getConfigLink() {
		return 'module.php?mod=' . $this->getName() . '&amp;mod_action=admin_config';
	}

	// The GENDEX file contains references to all none private individuals.
	private function createGendex() {
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
				echo $this->addMessage(I18N::translate('The gendex.txt file can not be created automatically. Try to manually create an empty text file in the root of your webtrees installation, called “gendex.txt”. Set the file permissions to 644.'), 'danger');
			} else {
				$this->writeGendexFile($handle, $data);
				chmod($file, 0644);
				echo $this->addMessage(I18N::translate('Your gendex.txt file has been created.'), 'success');
			}
		} else {
			if (!$handle = fopen($file, 'w')) {
				echo $this->addMessage(I18N::translate('Writing to the gendex.txt file failed. Be sure you have set the right file permissions (644).'), 'danger');
			} else {
				$this->writeGendexFile($handle, $data);
				echo $this->addMessage(I18N::translate('Your gendex.txt file has been updated.'), 'success');
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

return new FancyGendexModule;
