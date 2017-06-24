<?php
/*
 * webtrees: online genealogy
 * Copyright (C) 2017 webtrees development team
 * Copyright (C) 2017 JustCarmen
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
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\Functions\FunctionsDate;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Tree;

/**
 * Class Fancy Gendex
 */
class FancyGendexClass extends FancyGendexModule {

  /** @var string filename */
  private $file;

  /** @var string  filename of the temporary file */
  private $tmpfile;

  // The GENDEX file contains references to all none private individuals.
  protected function createGendex() {
    // create GENDEX text file
    $this->file    = WT_ROOT . 'gendex.txt';
    $this->tmpfile = WT_DATA_DIR . basename($this->file) . '.tmp';

    if (file_exists($this->file)) {
      try {
        // To avoid timeout/diskspace/etc, write to a temporary file first
        $stream = fopen($this->tmpfile, 'w');
        $this->writeGendexFile($stream);
        echo FlashMessages::addMessage(I18N::translate('The GENDEX file has been updated.'), 'success');
      } catch (\ErrorException $ex) {
        echo FlashMessages::addMessage(I18N::translate('Writing to the GENDEX file failed. Be sure you have set the right file permissions (644).') . '<hr><samp dir="ltr">' . $ex->getMessage() . '</samp>', 'danger');
      }
    } else {
      // make our GENDEX text file if it does not exist.
      try {
        $stream = fopen($this->tmpfile, 'w');
        $this->writeGendexFile($stream);
        chmod($this->file, 0644);
        echo FlashMessages::addMessage(I18N::translate('The GENDEX file has been created.'), 'success');
      } catch (\ErrorException $ex) {
        echo FlashMessages::addMessage(I18N::translate('The GENDEX file can not be created automatically. Try to manually create an empty text file in the root of your webtrees installation, called “gendex.txt”. Set the file permissions to 644.') . '<hr><samp dir="ltr">' . $ex->getMessage() . '</samp>', 'danger');
      }
    }
  }

  // Get a list of all the individuals for the choosen gedcom
  private function getAllNames($tree_id) {
    $sql  = "SELECT SQL_CACHE n_id as id, UPPER(n_surname) as surname, n_givn as givn FROM `##name` WHERE n_file = :tree_id AND n_type = 'NAME' ORDER BY n_sort ASC";
    $args = [
        'tree_id' => $tree_id
    ];

    foreach (Database::prepare($sql)->execute($args)->fetchAll() as $row) {
      $list[] = [
          'ID'      => $row->id,
          'SURNAME' => $this->printChars($row->surname),
          'GIVN'    => $this->printChars($row->givn)
      ];
    }
    return $list;
  }

  private function printChars($string) {
    if ($this->getPreference('FG_REPLACE_CHARS')) {
      $replace = [
          '&lt;'   => '', '&gt;'   => '', '&#039;' => '', '&amp;'  => '',
          '&quot;' => '', 'À'      => 'A', 'Á'      => 'A', 'Â'      => 'A', 'Ã'      => 'A', 'Ä'      => 'Ae',
          '&Auml;' => 'A', 'Å'      => 'A', 'Ā'      => 'A', 'Ą'      => 'A', 'Ă'      => 'A', 'Æ'      => 'Ae',
          'Ç'      => 'C', 'Ć'      => 'C', 'Č'      => 'C', 'Ĉ'      => 'C', 'Ċ'      => 'C', 'Ď'      => 'D', 'Đ'      => 'D',
          'Ð'      => 'D', 'È'      => 'E', 'É'      => 'E', 'Ê'      => 'E', 'Ë'      => 'E', 'Ē'      => 'E',
          'Ę'      => 'E', 'Ě'      => 'E', 'Ĕ'      => 'E', 'Ė'      => 'E', 'Ĝ'      => 'G', 'Ğ'      => 'G',
          'Ġ'      => 'G', 'Ģ'      => 'G', 'Ĥ'      => 'H', 'Ħ'      => 'H', 'Ì'      => 'I', 'Í'      => 'I',
          'Î'      => 'I', 'Ï'      => 'I', 'Ī'      => 'I', 'Ĩ'      => 'I', 'Ĭ'      => 'I', 'Į'      => 'I',
          'İ'      => 'I', 'Ĳ'      => 'IJ', 'Ĵ'      => 'J', 'Ķ'      => 'K', 'Ł'      => 'K', 'Ľ'      => 'K',
          'Ĺ'      => 'K', 'Ļ'      => 'K', 'Ŀ'      => 'K', 'Ñ'      => 'N', 'Ń'      => 'N', 'Ň'      => 'N',
          'Ņ'      => 'N', 'Ŋ'      => 'N', 'Ò'      => 'O', 'Ó'      => 'O', 'Ô'      => 'O', 'Õ'      => 'O',
          'Ö'      => 'Oe', '&Ouml;' => 'Oe', 'Ø'      => 'O', 'Ō'      => 'O', 'Ő'      => 'O', 'Ŏ'      => 'O',
          'Œ'      => 'OE', 'Ŕ'      => 'R', 'Ř'      => 'R', 'Ŗ'      => 'R', 'Ś'      => 'S', 'Š'      => 'S',
          'Ş'      => 'S', 'Ŝ'      => 'S', 'Ș'      => 'S', 'Ť'      => 'T', 'Ţ'      => 'T', 'Ŧ'      => 'T',
          'Ț'      => 'T', 'Ù'      => 'U', 'Ú'      => 'U', 'Û'      => 'U', 'Ü'      => 'Ue', 'Ū'      => 'U',
          '&Uuml;' => 'Ue', 'Ů'      => 'U', 'Ű'      => 'U', 'Ŭ'      => 'U', 'Ũ'      => 'U', 'Ų'      => 'U',
          'Ŵ'      => 'W', 'Ý'      => 'Y', 'Ŷ'      => 'Y', 'Ÿ'      => 'Y', 'Ź'      => 'Z', 'Ž'      => 'Z',
          'Ż'      => 'Z', 'Þ'      => 'T', 'à'      => 'a', 'á'      => 'a', 'â'      => 'a', 'ã'      => 'a',
          'ä'      => 'ae', '&auml;' => 'ae', 'å'      => 'a', 'ā'      => 'a', 'ą'      => 'a', 'ă'      => 'a',
          'æ'      => 'ae', 'ç'      => 'c', 'ć'      => 'c', 'č'      => 'c', 'ĉ'      => 'c', 'ċ'      => 'c',
          'ď'      => 'd', 'đ'      => 'd', 'ð'      => 'd', 'è'      => 'e', 'é'      => 'e', 'ê'      => 'e',
          'ë'      => 'e', 'ē'      => 'e', 'ę'      => 'e', 'ě'      => 'e', 'ĕ'      => 'e', 'ė'      => 'e',
          'ƒ'      => 'f', 'ĝ'      => 'g', 'ğ'      => 'g', 'ġ'      => 'g', 'ģ'      => 'g', 'ĥ'      => 'h',
          'ħ'      => 'h', 'ì'      => 'i', 'í'      => 'i', 'î'      => 'i', 'ï'      => 'i', 'ī'      => 'i',
          'ĩ'      => 'i', 'ĭ'      => 'i', 'į'      => 'i', 'ı'      => 'i', 'ĳ'      => 'ij', 'ĵ'      => 'j',
          'ķ'      => 'k', 'ĸ'      => 'k', 'ł'      => 'l', 'ľ'      => 'l', 'ĺ'      => 'l', 'ļ'      => 'l',
          'ŀ'      => 'l', 'ñ'      => 'n', 'ń'      => 'n', 'ň'      => 'n', 'ņ'      => 'n', 'ŉ'      => 'n',
          'ŋ'      => 'n', 'ò'      => 'o', 'ó'      => 'o', 'ô'      => 'o', 'õ'      => 'o', 'ö'      => 'oe',
          '&ouml;' => 'oe', 'ø'      => 'o', 'ō'      => 'o', 'ő'      => 'o', 'ŏ'      => 'o', 'œ'      => 'oe',
          'ŕ'      => 'r', 'ř'      => 'r', 'ŗ'      => 'r', 'š'      => 's', 'ù'      => 'u', 'ú'      => 'u',
          'û'      => 'u', 'ü'      => 'ue', 'ū'      => 'u', '&uuml;' => 'ue', 'ů'      => 'u', 'ű'      => 'u',
          'ŭ'      => 'u', 'ũ'      => 'u', 'ų'      => 'u', 'ŵ'      => 'w', 'ý'      => 'y', 'ÿ'      => 'y',
          'ŷ'      => 'y', 'ž'      => 'z', 'ż'      => 'z', 'ź'      => 'z', 'þ'      => 't', 'ß'      => 'ss',
          'ſ'      => 'ss', 'ый'     => 'iy', 'А'      => 'A', 'Б'      => 'B', 'В'      => 'V', 'Г'      => 'G',
          'Д'      => 'D', 'Е'      => 'E', 'Ё'      => 'YO', 'Ж'      => 'ZH', 'З'      => 'Z', 'И'      => 'I',
          'Й'      => 'Y', 'К'      => 'K', 'Л'      => 'L', 'М'      => 'M', 'Н'      => 'N', 'О'      => 'O',
          'П'      => 'P', 'Р'      => 'R', 'С'      => 'S', 'Т'      => 'T', 'У'      => 'U', 'Ф'      => 'F',
          'Х'      => 'H', 'Ц'      => 'C', 'Ч'      => 'CH', 'Ш'      => 'SH', 'Щ'      => 'SCH', 'Ъ'      => '',
          'Ы'      => 'Y', 'Ь'      => '', 'Э'      => 'E', 'Ю'      => 'YU', 'Я'      => 'YA', 'а'      => 'a',
          'б'      => 'b', 'в'      => 'v', 'г'      => 'g', 'д'      => 'd', 'е'      => 'e', 'ё'      => 'yo',
          'ж'      => 'zh', 'з'      => 'z', 'и'      => 'i', 'й'      => 'y', 'к'      => 'k', 'л'      => 'l',
          'м'      => 'm', 'н'      => 'n', 'о'      => 'o', 'п'      => 'p', 'р'      => 'r', 'с'      => 's',
          'т'      => 't', 'у'      => 'u', 'ф'      => 'f', 'х'      => 'h', 'ц'      => 'c', 'ч'      => 'ch',
          'ш'      => 'sh', 'щ'      => 'sch', 'ъ'      => '', 'ы'      => 'y', 'ь'      => '', 'э'      => 'e',
          'ю'      => 'yu', 'я'      => 'ya'
      ];

      return str_replace(array_keys($replace), $replace, $string);
    } else {
      return $string;
    }
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
          ->execute([
              'fact'    => $fact,
              'xref'    => $xref,
              'tree_id' => $tree->getTreeId()])
          ->fetchOneRow();
      if ($row) {
        $day   = $row->d_day > 0 ? $row->d_day . ' ' : '';
        $month = !empty($row->d_month) ? $row->d_month . ' ' : '';
        $year  = $row->d_year > 0 ? $row->d_year : '';
        $date  = $day . $month . $year;
        return $date;
      }
    }
  }

  private function writeGendexChunks($tree, $stream) {
    $buffer = '';
    $indis  = $this->getAllNames($tree->getTreeId());
    foreach ($indis as $indi) {
      $xref   = $indi['ID'];
      $record = Individual::getInstance($xref, $tree);
      if ($record && $record->canShowName(Auth::PRIV_PRIVATE)) {
        $buffer .= $record->getXref() . '&ged=' . $tree->getName() . '|' . $indi['SURNAME'] . '|' . $indi['GIVN'] . ' /' . $indi['SURNAME'] . '/|' . $this->printDate(['BIRT', 'BAPM', 'CHR'], $xref, $tree) . '|' . $this->printChars($record->getBirthPlace()) . '|' . $this->printDate(['DEAT', 'BURI'], $xref, $tree) . '|' . $this->printChars($record->getDeathPlace()) . '|' . PHP_EOL;
        if (strlen($buffer) > 65535) {
          fwrite($stream, $buffer);
          $buffer = '';
        }
      }
    }
    return $buffer;
  }

  private function writeGendexContent($stream) {
    foreach (Tree::getAll() as $tree) {
      if ($tree->getPreference('FANCY_GENDEX')) {
        $buffer = $this->writeGendexChunks($tree, $stream);
        fwrite($stream, $buffer);
      }
    }
  }

  private function writeGendexFile($stream) {
    $comment = ';;Generated with ' . WT_WEBTREES . ' ' . WT_VERSION . ' on ' . strip_tags(FunctionsDate::formatTimestamp(WT_TIMESTAMP + WT_TIMESTAMP_OFFSET)) . '|' . PHP_EOL;
    #UTF-8 - Add byte order mark
    fwrite($stream, pack('CCC', 0xef, 0xbb, 0xbf));
    fwrite($stream, $comment);
    $this->writeGendexContent($stream);
    fclose($stream);
    rename($this->tmpfile, $this->file);
  }

}
