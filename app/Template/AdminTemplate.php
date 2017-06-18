<?php
/*
 * webtrees: online genealogy
 * Copyright (C) 2018 JustCarmen (http://www.justcarmen.nl)
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
namespace JustCarmen\WebtreesAddOns\FancyGendex\Template;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Bootstrap4;
use Fisharebest\Webtrees\Controller\PageController;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use JustCarmen\WebtreesAddOns\FancyGendex\FancyGendexClass;

class AdminTemplate extends FancyGendexClass {

	protected function pageContent() {
		$controller = new PageController;
		return
			$this->pageHeader($controller) .
			$this->pageBody($controller);
	}

	private function pageHeader(PageController $controller) {
		$controller
			->restrictAccess(Auth::isAdmin())
			->setPageTitle($this->getTitle())
			->pageHeader();
	}

	private function pageBody(PageController $controller) {
		echo Bootstrap4::breadcrumbs([
			'admin.php'			 => I18N::translate('Control panel'),
			'admin_modules.php'	 => I18N::translate('Module administration'),
			], $controller->getPageTitle());
		?>
		<h1><?= $controller->getPageTitle() ?></h1>
		<p><?= I18N::translate('A GENDEX file is an index of personal data and a short page URL. It is used to index a genealogical website by a genealogical search engine. The idea behind it is to join numerous pedigrees of individual genealogical researchers to a central database, while the individual genealogical researchers are still keeping  all control over their data. Unlike a GEDCOM file a GENDEX file contains no information about the family relationships between individuals. This means the file is useless without the corresponding website.') ?></p>
		<p><?= I18N::translate('The GENDEX file will only contain public data.') ?></p>
		<hr style="border-color:#ccc">
		<form method="post" action="module.php?mod=<?= $this->getName() ?>&amp;mod_action=admin_config">
			<input type="hidden" name="action" value="save"><?= Filter::getCsrf() ?>
			<h4><?= I18N::translate('Which family trees should be included in the GENDEX file?') ?></h4>
			<div class="form-group">
				<?php
				foreach (Tree::getAll() as $tree) {
					echo Bootstrap4::checkbox($tree->getTitle(), false, ['name' => 'FG' . $tree->getTreeId(), 'checked' => $tree->getPreference('FANCY_GENDEX')]);
				}
				?>
			</div>
			<div class="form-group">
				<?= Bootstrap4::checkbox(I18N::translate('Replace special characters in the GENDEX file'), false, ['name' => 'FG_REPLACE_CHARS', 'checked' => $this->getPreference('FG_REPLACE_CHARS')]) ?>
				<p class="small muted"><?= I18N::translate('Some GENDEX search engines do not display special characters properly. If you encounter any problems you might get better results by enabling this setting.') ?></p>
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
				<?= $button_text ?>
			</button>
		</form>
		<hr style="border-color:#ccc">
		<?php $gendex_url = WT_BASE_URL . 'gendex.txt'; ?>
		<?php if (file_exists(WT_ROOT . 'gendex.txt')): ?>
			<p><?= I18N::translate('Click on the link below to view your GENDEX file.') ?></p>
			<p><a href="<?= $gendex_url; ?>" target="_blank"><?= $gendex_url ?></a></p>
			<p><?= I18N::translate('To tell search engines that a GENDEX file is available, you should submit the url above to the genealogical search engine of your choice.') ?></p>
			<p><?= I18N::translate('The search engines below are known to accept the GENDEX textfile created by this module:') ?></p>
			<ul>
				<li>
					<a href="http://www.gendexnetwork.org" target="_blank">The Gendex Network</a> (<?= I18N::translate('English/International') ?>)
				</li>
				<li>
					<a href="http://www.familytreeseeker.com/?l=en" target="_blank">Familytreeseeker</a> (<?= I18N::translate('English/International') ?>)
				</li>
				<li>
					<a href="http://www.stamboomzoeker.nl/?l=nl" target="_blank">Stamboomzoeker</a> (<?= I18N::translate('Dutch') ?>)
				</li>
			</ul>
			<p><?= I18N::translate('You need this url (without the quotes) as general url to the individual pages when submitting to Stamboomzoeker or Familytreeseeker:'); ?> “<?= WT_BASE_URL ?>individual.php?pid=”</p>
			<p class="alert alert-info"><?= I18N::translate('Note: the GENDEX text file is not automatically updated. If you have made changes to your tree you need to update the GENDEX text file manually by clicking on the button. You do <b>not</b> need to update your subscription at the genealogical search engine.') ?></p>
			<hr>
		<?php endif; ?>
		<?php
	}

}
