<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2017 webtrees development team
 * Copyright (C) 2017 JustCarmen
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
namespace JustCarmen\WebtreesAddOns\FancyGendex;

use Composer\Autoload\ClassLoader;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Tree;
use JustCarmen\WebtreesAddOns\FancyGendex\Template\AdminTemplate;

class FancyGendexModule extends AbstractModule implements ModuleConfigInterface {

	const CUSTOM_VERSION	 = '1.7.9';
	const CUSTOM_WEBSITE	 = 'http://www.justcarmen.nl/fancy-modules/fancy-gendex/';

	/** @var string location of the Fancy Gendex module files */
	var $directory;

	public function __construct() {
		parent::__construct('fancy_gendex');

		$this->directory = WT_MODULES_DIR . $this->getName();

		// register the namespaces
		$loader = new ClassLoader();
		$loader->addPsr4('JustCarmen\\WebtreesAddOns\\FancyGendex\\', $this->directory . '/app');
		$loader->register();
	}

	/**
	 * Get the module class.
	 * 
	 * Class functions are called with $this inside the source directory.
	 */
	private function module() {
		return new FancyGendexClass;
	}

	// Extend Module
	public function getTitle() {
		return /* I18N: Name of a module */ I18N::translate('Fancy Gendex');
	}

	// Extend Module
	public function getDescription() {
		return /* I18N: Description of the module */ I18N::translate('Generate GENDEX file for genealogical search engines.');
	}

	// Extend Module
	public function modAction($mod_action) {
		switch ($mod_action) {
			case 'admin_config':
				if (Filter::post('action') == 'save' && Filter::checkCsrf()) {
					foreach (Tree::getAll() as $tree) {
						$tree->setPreference('FANCY_GENDEX', Filter::postBool('FG' . $tree->getTreeId()));
					}

					$this->setSetting('FG_REPLACE_CHARS', Filter::postBool('FG_REPLACE_CHARS'));
					$this->module()->createGendex();
				}
				$template = new AdminTemplate;
				return $template->pageContent();
			default:
				http_response_code(404);
				break;
		}
	}

	// Implement ModuleConfigInterface
	public function getConfigLink() {
		return 'module.php?mod=' . $this->getName() . '&amp;mod_action=admin_config';
	}

}

return new FancyGendexModule;
