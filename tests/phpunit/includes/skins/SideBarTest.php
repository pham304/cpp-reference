<?php

use MediaWiki\MediaWikiServices;

/**
 * @group Skin
 */
class SideBarTest extends MediaWikiLangTestCase {

	/**
	 * A skin template, reinitialized before each test
	 * @var SkinTemplate
	 */
	private $skin;
	/** @var string[][] Local cache for sidebar messages */
	private $messages;

	/** Build $this->messages array */
	private function initMessagesHref() {
		# List of default messages for the sidebar. The sidebar doesn't care at
		# all whether they are full URLs, interwiki links or local titles.
		$URL_messages = [
			'mainpage',
			'portal-url',
			'currentevents-url',
			'recentchanges-url',
			'randompage-url',
			'helppage',
		];

		$messageCache = MediaWikiServices::getInstance()->getMessageCache();
		# We're assuming that isValidURI works as advertised: it's also
		# tested separately, in tests/phpunit/includes/HttpTest.php.
		foreach ( $URL_messages as $m ) {
			$titleName = $messageCache->get( $m );
			if ( Http::isValidURI( $titleName ) ) {
				$this->messages[$m]['href'] = $titleName;
			} else {
				$title = Title::newFromText( $titleName );
				$this->messages[$m]['href'] = $title->getLocalURL();
			}
		}
	}

	protected function setUp(): void {
		parent::setUp();
		$this->initMessagesHref();
		$this->skin = new SkinTemplate();
		$this->skin->getContext()->setLanguage( 'en' );
	}

	/**
	 * Internal helper to test the sidebar
	 * @param array $expected
	 * @param string $text
	 * @param string $message (Default: '')
	 * @todo this assert method to should be converted to a test using a dataprovider.
	 */
	private function assertSideBar( $expected, $text, $message = '' ) {
		$bar = [];
		$this->skin->addToSidebarPlain( $bar, $text );
		$this->assertEquals( $expected, $bar, $message );
	}

	/**
	 * @covers SkinTemplate::addToSidebarPlain
	 */
	public function testSidebarWithOnlyTwoTitles() {
		$this->assertSideBar(
			[
				'Title1' => [],
				'Title2' => [],
			],
			'* Title1
* Title2
'
		);
	}

	/**
	 * @covers SkinTemplate::addToSidebarPlain
	 */
	public function testExpandMessages() {
		$this->assertSideBar(
			[ 'Title' => [
				[
					'text' => 'Help',
					'href' => $this->messages['helppage']['href'],
					'id' => 'n-help',
					'active' => null
				]
			] ],
			'* Title
** helppage|help
'
		);
	}

	/**
	 * @covers SkinTemplate::addToSidebarPlain
	 */
	public function testExternalUrlsRequireADescription() {
		$this->setMwGlobals( [
			'wgNoFollowLinks' => true,
			'wgNoFollowDomainExceptions' => [],
			'wgNoFollowNsExceptions' => [],
		] );
		$this->assertSideBar(
			[ 'Title' => [
				# ** https://www.mediawiki.org/| Home
				[
					'text' => 'Home',
					'href' => 'https://www.mediawiki.org/',
					'id' => 'n-Home',
					'active' => null,
					'rel' => 'nofollow',
				],
				# ** http://valid.no.desc.org/
				# ... skipped since it is missing a pipe with a description
			] ],
			'* Title
** https://www.mediawiki.org/| Home
** http://valid.no.desc.org/
'
		);
	}

	/**
	 * T35321 - Make sure there's a | after transforming.
	 * @group Database
	 * @covers SkinTemplate::addToSidebarPlain
	 */
	public function testTrickyPipe() {
		$this->assertSideBar(
			[ 'Title' => [
				# The first 2 are skipped
				# Doesn't really test the url properly
				# because it will vary with $wgArticlePath et al.
				# ** Baz|Fred
				[
					'text' => 'Fred',
					'href' => Title::newFromText( 'Baz' )->getLocalURL(),
					'id' => 'n-Fred',
					'active' => null,
				],
				[
					'text' => 'title-to-display',
					'href' => Title::newFromText( 'page-to-go-to' )->getLocalURL(),
					'id' => 'n-title-to-display',
					'active' => null,
				],
			] ],
			'* Title
** {{PAGENAME|Foo}}
** Bar
** Baz|Fred
** {{PLURAL:1|page-to-go-to{{int:pipe-separator/en}}title-to-display|branch not taken}}
'
		);
	}

	#### Attributes for external links ##########################
	private function getAttribs() {
		# Sidebar text we will use everytime
		$text = '* Title
** https://www.mediawiki.org/| Home';

		$bar = [];
		$this->skin->addToSidebarPlain( $bar, $text );

		return $bar['Title'][0];
	}

	/**
	 * Simple test to verify our helper assertAttribs() is functional
	 * @coversNothing
	 */
	public function testTestAttributesAssertionHelper() {
		$this->setMwGlobals( [
			'wgNoFollowLinks' => true,
			'wgNoFollowDomainExceptions' => [],
			'wgNoFollowNsExceptions' => [],
			'wgExternalLinkTarget' => false,
		] );
		$attribs = $this->getAttribs();

		$this->assertArrayHasKey( 'rel', $attribs );
		$this->assertEquals( 'nofollow', $attribs['rel'] );

		$this->assertArrayNotHasKey( 'target', $attribs );
	}

	/**
	 * Test $wgNoFollowLinks in sidebar
	 * @covers Skin::addToSidebarPlain
	 */
	public function testRespectWgnofollowlinks() {
		$this->setMwGlobals( 'wgNoFollowLinks', false );

		$attribs = $this->getAttribs();
		$this->assertArrayNotHasKey( 'rel', $attribs,
			'External URL in sidebar do not have rel=nofollow when $wgNoFollowLinks = false'
		);
	}

	/**
	 * Test $wgExternaLinkTarget in sidebar
	 * @dataProvider dataRespectExternallinktarget
	 * @covers Skin::addToSidebarPlain
	 */
	public function testRespectExternallinktarget( $externalLinkTarget ) {
		$this->setMwGlobals( 'wgExternalLinkTarget', $externalLinkTarget );

		$attribs = $this->getAttribs();
		$this->assertArrayHasKey( 'target', $attribs );
		$this->assertEquals( $attribs['target'], $externalLinkTarget );
	}

	public static function dataRespectExternallinktarget() {
		return [
			[ '_blank' ],
			[ '_self' ],
		];
	}
}
