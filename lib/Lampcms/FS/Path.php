<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms\FS;

/**
 * Class for preparing and resolving file path
 * based on id using dec2hex and hex2dec file storage
 * scheme.
 *
 * @author Dmitri Snytkine
 *
 */
class Path{
	
	/**
	 * It is used to verify or create the path of the file (using HEX logic)
	 *
	 * @param int $intResourceId  resource id
	 * the path will be created based on this integer
	 *
	 * @param string $destinationDir directory in which
	 * the path will be created.
	 *
	 * @return string a hex path (without file extension)
	 * OR a full path, including the destinationDir prefix
	 * if $bReturnFullPath param is true
	 *
	 */
	public static final function prepare($intResourceId, $destinationDir = '', $bReturnFullPath = false){

		if(!is_numeric($intResourceId)){
			throw new \InvalidArgumentException('$intResourceId must be numeric, ideally an integer. Was: '.$intResourceId);
		}

		/**
		 * Resource id is converted to hex number
		 */
		$destinationDir = \trim((string)$destinationDir);
		$strHex = dechex((int)$intResourceId);
		$strHex = strtoupper($strHex);
		$arrTemp = array();
		$intCount = 0;
		$strPath = '';
		$strFullPathToOrig = '';
		do {
			$intCount++;
			$intRes = preg_match("/([0-9A-F]{1,2})([0-9A-F]*)/", $strHex, $arrTemp);
			//$strHex = $arrTemp[2];
			//d('$strHex: '.$strHex);
			d('$arrTemp: '.print_r($arrTemp, 1));

			$strPath .= ''.$arrTemp[1];
			if ($arrTemp && ('' !== $arrTemp[2])) {
				$strHex = $arrTemp[2];
				$strPath .= '/';
				//   PATH to Location
				$strFullPathToOrig = $destinationDir.$strPath;

				d('$strFullPathToOrig: '.$strFullPathToOrig);

				if (!\file_exists($strFullPathToOrig) && !\is_dir($strFullPathToOrig)) {
					if (!\mkdir($strFullPathToOrig, 0777)) {
						throw new \Lampcms\DevException('Cannot create directory '.$strFullPathToOrig);
					}
				}
			}
		} while ($intRes && ($intCount < 10) && ('' !== $arrTemp[2]));

		$ret =  ($bReturnFullPath) ? $destinationDir.$strPath : $strPath;

		d(' $ret: ' .$ret);

		return $ret;
	}


	/**
	 * Converts the hex path to integer
	 * for example:
	 * 3E/A
	 * is converted to 1002
	 * @param string $hex
	 * a hex-like path
	 *
	 * @return integer
	 *
	 */
	public static function hex2dec($hex){
		$hex = str_replace('/', '', $hex);

		return hexdec($hex);
	}
	
}
