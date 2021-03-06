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



namespace Lampcms\Modules\Observers;

use \Lampcms\Utf8String;
use \Lampcms\Facebook;

/**
 * This observer will post Question or Answer link
 * to Facebook API (to Question/Answer poster's Wall)
 *
 * @author Dmitri Snytkine
 *
 */
class PostFacebook extends \Lampcms\Observer
{
	/**
	 * Facebook configuration section
	 * from !config.ini
	 *
	 * @var array
	 */
	protected $aFB = array();


	public function main(){
		d('get event: '.$this->eventName);
		$a = $this->oRegistry->Request->getArray();
		if(empty($a['facebook'])){
			d('facebook checkbox not checked');
			/**
			 * Set the preference in Viewer object
			 * for that "Post to Faebook" checkbox to be not checked
			 * This is just in case it was checked before
			 */
			$this->oRegistry->Viewer['b_fb'] = false;
			return;
		}

		/**
		 * First if this site does not have support for Facebook API
		 * OR if User does not have Facebook credentials then
		 * there is nothing to do here
		 * This is unlikely because user without Facebook credentials
		 * will not get to see the checkbox to post to Facebook
		 * but still it's better to check just to be sure
		 */
		if(!extension_loaded('curl')){
			d('curl extension not present, exiting');
			return;
		}

		try{
			$this->aFB = $this->oRegistry->Ini->getSection('FACEBOOK');
			if(empty($this->aFB) || empty($this->aFB['API_KEY'])){
				d('Facebook API not enabled on this site');
				return;;
			}
		} catch (\Lampcms\IniException $e){
			d($e->getMessage());
			return;
		}

		if('' === (string)$this->oRegistry->Viewer->getFacebookToken()){
			d('User does not have Facebook token');
			return;
		}

		/**
		 * Now we know that user checked that checkbox
		 * to post content to Facebook
		 * and we now going to save this preference
		 * in User object
		 *
		 */
		$this->oRegistry->Viewer['b_fb'] = true;

		switch($this->eventName){
			case 'onNewQuestion':
			case 'onNewAnswer':
				$this->post();
				break;
		}
	}


	/**
	 * Post a Link to this question or answer
	 * To User's Facebook Wall
	 *
	 */
	protected function post(){

		try{
			$reward = \Lampcms\Points::SHARED_CONTENT;
			$User = $this->oRegistry->Viewer;
			$oFB = Facebook::factory($this->oRegistry);
			$oResource = $this->obj;
			$Mongo = $this->oRegistry->Mongo;
			$logo = (!empty($this->aFB['SITE_LOGO'])) ? $this->aFB['SITE_LOGO'] : null;
			/**
			 * @todo Translate String(s) of caption
			 * It appears on Facebook Wall under the link
			 * @var string
			 */
			$caption = ($this->obj instanceof \Lampcms\Question) ? 'Please click if you can answer this question' : 'I answered this question';
			d('cp');
			$description = Utf8String::factory($this->obj['b'], 'utf-8', true)->asPlainText()->valueOf();
			d('cp');
		} catch (\Exception $e){
			d('Unable to post to facebook because of this exception: '.$e->getMessage().' in file: '.$e->getFile().' on line: '.$e->getLine());
			return;
		}

		$func = function() use ($oFB, $oResource, $User, $reward, $Mongo, $logo, $caption, $description){

			$result = null;
			/**
			 * @todo Translate string "caption"
			 */
			$aData = array(
			'link' => $oResource->getUrl(),
			'name' => $oResource['title'],
			'caption' => $caption,
			'description' => $description);

			if(!empty($logo) && ('http' == substr($logo, 0, 4))){
				$aData['picture'] = $logo;
			}

			try{
				$result = $oFB->postUpdate($aData);
					
			} catch(\Exception $e){
				// does not matter
			}
			
			if(!empty($result) && (false !== $decoded = json_decode($result, true)) ){
				d('Got result from Facebook API: '.print_r($decoded, 1));
				/**
				 * If status is OK 
				 * then reward the user with points!
				 */
				if(!empty($decoded['id'])){
					$User->setReputation($reward);

					/**
					 * Now need to also record Facebook id
					 * to FB_STATUSES collection
					 */
					try {	
						/**
						 * 
						 * Later can query Facebook to find
						 * replies to this post and add them
						 * as "comments" to this Question or Answer
						 *
						 * HINT: if i_rid !== i_qid then it's an ANSWER
						 * if these are the same then it's a Question
						 * @var array
						 */				
						/*$coll = $Mongo->FB_STATUSES;
						$coll->ensureIndex(array('i_uid' => 1));
						$coll->ensureIndex(array('status_id' => 1));

						$status_id = $decoded['id'];
						$uid = $User->getUid();
						$rid = $oResource->getResourceId();
						$qid = $oResource->getQuestionId();

						
						$aData = array(
						'status_id' => $status_id, 
						'i_uid' => $uid, 
						'i_rid' => $rid,
						'i_qid' => $qid,
						'i_ts' => time(),
						'h_ts' => date('r')
						);

						$coll->save($aData);
						*/
						/**
						 * Also save fb_status to QUESTIONS or ANSWERS
						 * collection.
						 * This way later on (maybe way later...)
						 * We can add a function so that if user edits 
						 * Post on the site we can also edit it 
						 * on Tumblr via API
						 *
						 */
						$oResource['fb_status'] = $status_id;
						$oResource->save();

					} catch (\Exception $e){
						e('Unable to save data to FB_STATUSES collection because of '.$e->getMessage().' in file: '.$e->getFile().' on line: '.$e->getLine());
					}
				}
			}
		};

		\Lampcms\runLater($func);
	}

}
