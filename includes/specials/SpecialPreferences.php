<?php
/**
 * Implements Special:Preferences
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 */

/**
 * A special page that allows users to change their preferences
 *
 * @ingroup SpecialPage
 */
class SpecialPreferences extends SpecialPage {
	function __construct() {
		parent::__construct( 'Preferences' );
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();
		$out = $this->getOutput();
		$out->disallowUserJs(); # Prevent hijacked user scripts from sniffing passwords etc.

		$this->requireLogin( 'prefsnologintext2' );
		$this->checkReadOnly();

		if ( $par == 'reset' ) {
			$this->showResetForm();

			return;
		}

		$out->addModules( 'mediawiki.special.preferences' );
		$out->addModuleStyles( 'mediawiki.special.preferences.styles' );

		if ( $this->getRequest()->getCheck( 'success' ) ) {
			$out->wrapWikiMsg(
				Html::rawElement(
					'div',
					array(
						'class' => 'mw-preferences-messagebox successbox',
						'id' => 'mw-preferences-success'
					),
					Html::element( 'p', array(), '$1' )
				),
				'savedprefs'
			);
		}

		$this->addHelpLink( 'Help:Preferences' );

		// Load the user from the master to reduce CAS errors on double post (T95839)
		$user = $this->getUser()->getInstanceForUpdate() ?: $this->getUser();

		$htmlForm = Preferences::getFormObject( $user, $this->getContext() );
		$htmlForm->setSubmitCallback( array( 'Preferences', 'tryUISubmit' ) );
		$sectionTitles = $htmlForm->getPreferenceSections();

		$prefTabs = '';
		foreach ( $sectionTitles as $key ) {
			$prefTabs .= Html::rawElement( 'li',
				array(
					'role' => 'presentation',
					'class' => ( $key === 'personal' ) ? 'selected' : null
				),
				Html::rawElement( 'a',
					array(
						'id' => 'preftab-' . $key,
						'role' => 'tab',
						'href' => '#mw-prefsection-' . $key,
						'aria-controls' => 'mw-prefsection-' . $key,
						'aria-selected' => ( $key === 'personal' ) ? 'true' : 'false',
						'tabIndex' => ( $key === 'personal' ) ? 0 : -1,
					),
					$htmlForm->getLegend( $key )
				)
			);
		}

		$out->addHTML(
			Html::rawElement( 'ul',
				array(
					'id' => 'preftoc',
					'role' => 'tablist'
				),
				$prefTabs )
		);
		$htmlForm->show();
	}

	private function showResetForm() {
		if ( !$this->getUser()->isAllowed( 'editmyoptions' ) ) {
			throw new PermissionsError( 'editmyoptions' );
		}

		$this->getOutput()->addWikiMsg( 'prefs-reset-intro' );

		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $this->getPageTitle( 'reset' ) ); // Reset subpage
		$htmlForm = new HTMLForm( array(), $context, 'prefs-restore' );

		$htmlForm->setSubmitTextMsg( 'restoreprefs' );
		$htmlForm->setSubmitDestructive();
		$htmlForm->setSubmitCallback( array( $this, 'submitReset' ) );
		$htmlForm->suppressReset();

		$htmlForm->show();
	}

	public function submitReset( $formData ) {
		if ( !$this->getUser()->isAllowed( 'editmyoptions' ) ) {
			throw new PermissionsError( 'editmyoptions' );
		}

		$user = $this->getUser();
		$user->resetOptions( 'all', $this->getContext() );
		$user->saveSettings();

		$url = $this->getPageTitle()->getFullURL( 'success' );

		$this->getOutput()->redirect( $url );

		return true;
	}

	protected function getGroupName() {
		return 'users';
	}
}
