<?php

if ( !defined( 'MEDIAWIKI' ) ) {
				die( 'This file is a MediaWiki extension, it is not a valid entry point' );
}

require_once( dirname(__FILE__) . "/../CustomData/CustomData.php" );

# Internationalisation file
$dir = dirname(__FILE__) . '/';
$wgExtensionMessagesFiles['BreadCrumbs'] = $dir . 'BreadCrumbs.i18n.php';

$wgExtensionFunctions[] = 'wfSetupBreadCrumbs';

$wgExtensionCredits['parserhook']['BreadCrumbs'] = array( 'name' => 'BreadCrumbs', 'url' => 
'http://wikivoyage.org/tech/BreadCrumbs-Extension', 'author' => 'Roland Unger/Hans Musil',
'descriptionmsg' => 'bct-desc' );

$wgHooks['LanguageGetMagic'][]			 = 'wfBreadCrumbsParserFunction_Magic';

class BreadCrumbs
{
	var $mParserOptions = null;

	function BreadCrumbs()
	{
		# error_log( "Call BreadCrumbs constructor:" . $this->hmcounter, 0);

		$this->mPCache =& ParserCache::singleton();
	}

	function onFuncIsIn( &$parser, $supreg)
	{
		# error_log( "onFuncIsIn: " . $supreg, 0);

		# Tribute to Evan!
		$supreg = urldecode( $supreg);

		$nt = Title::newFromText( $supreg, $parser->mTitle->getNamespace());

		if( !is_object( $nt ) )
		{
			return '';
		};
	 
		$linktext = $this->shortTitle( $nt->getText());
		$id	 = $nt->getArticleID();
		$lnk = new Linker;
		$link = $lnk->makeKnownLinkObj( $nt, $linktext);

		$sr =	array( 	'id' => $id, 
									'linktext' => $linktext, 
									'link'		=> $link,
									'namespace' => $nt->getNamespace(),
									'DBkey' => $nt->getDBkey(),

							 );

		# error_log( "onFuncIsIn: isin=" . serialize( $sr), 0);

		global $wgCustomData;
		$wgCustomData->setParserData( $parser->mOutput, 'BcIsIn', $sr);

		return '';
	}

	function onParserBeforeTidy( &$parser, &$text)
	{
		/*
		 *	Assumes that mRevisionId is only set for primary wiki text when a new revision is saved.
		 * 	We need this in order to save IsIn info appropriately.
		 */
		if( $parser->mRevisionId)
		{
			$this->completeImplicitIsIn( $parser->mOutput, $parser->mTitle);
		};

		return true;
	}

	/*
	 * Generates an IsIn from title for subpages.
	 */
	function completeImplicitIsIn( &$ParserOutput, $Title)
	{
		global $wgCustomData;

		# Don't touch talk pages. Hm, realy?
		#
		# error_log( 'completeImplicitIsIn: Namespace for ' . $Title->getText() . ' : ' . $Title->getNamespace(), 0);
		if( $Title->getNamespace() % 2)
		{ 
			return;
		};

		$sr = $wgCustomData->getParserData( $ParserOutput, 'BcIsIn');
		if( $sr)
		{ 
			return;
		};

		# error_log( 'completeImplicitIsIn: No IsIn for ' . $Title->getText(), 0);

		$trail = explode( '/', $Title->getText());
		array_pop( $trail);

		if( !$trail)
		{
			return;
		};

		$nt = Title::makeTitle( $Title->getNamespace(), implode( '/', $trail));

		# $linktext = $this->shortTitle( array_pop( $trail));
		$linktext = array_pop( $trail);
		$id	 = $nt->getArticleID();
		$lnk = new Linker;
		$link = $lnk->makeKnownLinkObj( $nt, $linktext);

		$sr =	array( 	'id' => $id, 
									'linktext' => $linktext, 
									'link'		=> $link,
									'namespace' => $nt->getNamespace(),
									'DBkey' => $nt->getDBkey(),
							 );

		# error_log( "completeImplicitIsIn: isin=" . serialize( $sr), 0);

		$wgCustomData->setParserData( $ParserOutput, 'BcIsIn', $sr);
	}

	function shortTitle( $title)
	{
		$subparts = explode( '/', $title);
		return array_pop( $subparts);

		# return $title; 
		# return preg_replace( '/\s*\(.*?\)$/', '', $title); 
	}

	#
	# Hooked in from hook SkinTemplateOutputPageBeforeExec.
	#
	function onSkinTemplateOutputPageBeforeExec( &$SkTmpl, &$QuickTmpl )
	{
		# error_log( "Hook: put_urls_to_SkinTemplate", 0);

		if( !wfRunHooks('BreadCrumbsBeforeOutput', array(&$this, &$SkTmpl, &$QuickTmpl))){ return true;};

		global $wgCustomData, $wgOut, $wgTitle;

		# Parser hook onParserBeforeTidy doesn't trigger completition if only previewing.
		#
		$this->completeImplicitIsIn( $wgOut, $wgTitle);

		# error_log( "onSkinTemplateOutputPageBeforeExec: " . serialize( $wgCustomData->getPageData( $wgOut, 'BcIsIn')), 0);

		$sr = $wgCustomData->getPageData( $wgOut, 'BcIsIn');
		$bc_arr = $this->mkBcTrail( $sr);

		if( !$bc_arr){ return true;};

		# $subparts = explode( '/', $wgTitle->getText());
    # array_push( $bc_arr, $this->shortTitle( array_pop( $subparts)));
    array_push( $bc_arr, $this->shortTitle( $wgTitle->getText()));
		$bc = implode( ' '.wfMsgForContent( 'bct-delimiter' ).' ', $bc_arr);

		/*
		if( $wgTitle->getNamespace() == NS_MAIN)
		{
			$bc = '<span class="ldbNoLocation">&nbsp;!&nbsp;</span>'.$bc;
		};
		*/

		# $oldsubtitle = $QuickTmpl->haveData( 'subtitle' );
		$oldsubtitle = $QuickTmpl->data['subtitle'];

		# error_log( "onSkinTemplateOutputPageBeforeExec: " . $QuickTmpl->haveData( 'subtitle' ), 0);

		# $subtitle = $oldsubtitle ? '<p>'.$bc.'</p><p>'.$oldsubtitle.'</p>' : $bc;
		$subtitle = $oldsubtitle ? $bc."<br />\n".$oldsubtitle : $bc;
		# $subtitle = ($oldsubtitle && strpos( '<span class="subpages">&lt;', $oldsubtitle) !== 1) ? $bc."<br />\n".$oldsubtitle : $bc;
		$QuickTmpl->set( 'subtitle', $subtitle );

		wfDebug( "onSkinTemplateOutputPageBeforeExec: subtitle = $subtitle\noldsubtitle = $oldsubtitle\n");

		# error_log( "onSkinTemplateOutputPageBeforeExec: " . $QuickTmpl->haveData( 'subtitle' ), 0);

		return true;
	}


	function mkBcTrail( $sr)
	{
		# error_log( "Entring mkBcTrail.", 0);

		$bc_arr = array();
		if( !$sr){ return $bc_arr;};

		# Avoid cyclic trails.
		#
		$idStack = array();

		# Emergency break.
		#
    $cnt = 20;

		while( is_array( $sr) && $cnt--)
		{
			if( $sr[ 'link'] )
			{
				array_unshift( $bc_arr, $sr[ 'link'] );
			}else
			{
				# Mark redirects with italics.
				#
				$bc_arr[0] = '<i>'.$bc_arr[0].'</i>';
			};

			if( array_key_exists( $sr['id'], $idStack))
			{
				# error_log( "mkBcTrail: cyclic: " . $sr['linktext'] . '  ' . $sr['id'], 0);

				$bc_arr[0] = '<strike>'.$bc_arr[0].'</strike>';
				break;
			};

			# error_log( "mkBcTrail: " . $sr['linktext'] . '  ' . $sr['id'], 0);

			$idStack[ $sr['id'] ] = true;
			$sr = $this->getSupRegion( $sr);
		};

		return $bc_arr;
	}

	function getSupRegion( $oldsr)
	{
		global $wgCustomData;

		if( $oldsr[ 'id'] <= 0){ return null;};

		$pc = $this->getParserCache( $oldsr[ 'id']);
		$sr = $wgCustomData->getParserData( $pc, 'BcIsIn');

    #
		# We cannot be sure that cached page id is still valid since articles may have moved.
		#
		if( $sr)
		{ 
      $nt = Title::makeTitle( $sr[ 'namespace'], $sr[ 'DBkey']);
		}else
		{
			# Is Title a redirect?
			#
      $ot = Title::makeTitle( $oldsr[ 'namespace'], $oldsr[ 'DBkey']);
			$art = new Article( $ot);
			$nt = $art->followRedirect();

			# run an empty loop, bail out on double redirects since no title info given.
			$sr = array( 'namespace' => null, 'DBkey' => '', 'link' => '');
			# error_log( "getSupRegion: No sr for " . $ot->getText(), 0);
		};

		if( !is_object( $nt))
    {
			# error_log( "getSupRegion: nt is null.", 0);

			return null;
		};

		$sr[ 'id'] = $nt->getArticleID();

		return $sr;
	}

	function getParserCache( $pageid)
	{
		global $wgParserCacheExpireTime, $wgContLang, $wgMemc, $parserMemc;

		if( $pageid <= 0){ return null;};

		# We look for the most usual key.
		#
		# $key = wfMemcKey( 'pcache', 'idhash', "$pageid-0!1!0!!". $wgContLang->getCode() ."!2" );
		$key = wfMemcKey( 'pcache', 'idhash', "$pageid-0!1!1500!!". $wgContLang->getCode() ."!2" );
//		$parserOutput = $this->mPCache->mMemc->get( $key );
		$parserOutput = $parserMemc->get( $key );


		if( !is_object( $parserOutput ) )
		{
			$nt = Title::newFromId( $pageid);

			$fname = 'getParserCache';
			$dbr =& wfGetDB( DB_SLAVE );

			$tbl_page     = $dbr->tableName( 'page' );
			$tbl_revision = $dbr->tableName( 'revision' );
			$tbl_text     = $dbr->tableName( 'text' );
			$sql = "SELECT old_text, rev_timestamp
								FROM $tbl_page 
								JOIN $tbl_revision ON rev_id=page_latest 
								JOIN $tbl_text ON $tbl_text.old_id=rev_text_id 
								WHERE page_id= $pageid";

			$res = $dbr->query($sql, $fname);
			$row = $dbr->fetchObject( $res);
			$text = $row->old_text;
			$ts	 = $row->rev_timestamp;
			$dbr->freeResult( $res );

			$parserOutput = $this->parseBc( $text, $nt);

			$now = wfTimestampNow();
			$parserOutput->setCacheTime( $now );

			// Save the timestamp so that we don't have to load the revision row on views.
			$parserOutput->mTimestamp = wfTimestamp( TS_MW, $ts);

			if( $parserOutput->containsOldMagic() ){
						$expire = 3600; # 1 hour
			} else {
						$expire = $wgParserCacheExpireTime;
			}
//			$this->mPCache->mMemc->set( $key, $parserOutput, $expire );
			$parserMemc->set( $key, $parserOutput, $expire );
		};

		return $parserOutput;
	}

  function parseBc( $text, $Title)
	{
		global $wgParser;
		$parserOutput = $wgParser->parse( $text, $Title, $this->getParserOptions());
		$this->completeImplicitIsIn( $parserOutput, $Title);

		return $parserOutput;
	}

	function getParserOptions()
	{
		if( !$this->mParserOptions)
		{
			$this->mParserOptions = new ParserOptions;
		};

		return $this->mParserOptions;
	}

	#
	# Only for debuging.
	#
	function ParserAfterStrip( &$parser, &$text, &$strip_state)
	{
		# error_log( $text, 0);

		global $wgCustomData;
		global $wgTitle;

		# error_log( 'ParserAfterStrip: ' . $wgTitle->getPrefixedText(), 0);
		# error_log( 'ParserAfterStrip: ' . $parser->mTitle->getText(), 0);
		# error_log( 'ParserAfterStrip: RevId=' . $parser->mRevisionId, 0);
		# error_log( 'ParserAfterStrip: ' . $text, 0);

		return true;
	}


};



function wfSetupBreadCrumbs() {
				# global $wgParser, $wgMessageCache, $wgExtParserFunctions, $wgMessageCache, $wgHooks;
				global $wgParser, $wgHooks;

				global $wgBreadCrumbs;
				$wgBreadCrumbs		 = new BreadCrumbs;

				$wgParser->setFunctionHook( 'isin', array( &$wgBreadCrumbs, 'onFuncIsIn' ));

				# $wgHooks['ParserAfterStrip'][] = array( &$wgBreadCrumbs, 'ParserAfterStrip' );
				$wgHooks['ParserBeforeTidy'][] = array( &$wgBreadCrumbs, 'onParserBeforeTidy' );
				$wgHooks['SkinTemplateOutputPageBeforeExec'][] = 
												array( &$wgBreadCrumbs, 'onSkinTemplateOutputPageBeforeExec' );

}


function wfBreadCrumbsParserFunction_Magic( &$magicWords, $langCode )
{
	$magicWords['isin'] = array( 0, 'isin' );

	return true;
}

?>
