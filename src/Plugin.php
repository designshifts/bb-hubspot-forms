<?php

namespace BBHubspotForms;

use BBHubspotForms\Admin\EditorAssets;
use BBHubspotForms\Admin\SettingsPage;
use BBHubspotForms\Forms\CPT;
use BBHubspotForms\Forms\Renderer;
use BBHubspotForms\REST\AdminController;
use BBHubspotForms\REST\FormsController;
use BBHubspotForms\REST\SubmitController;

final class Plugin {
	public static function init(): void {
		self::load_dependencies();
		self::register_hooks();
	}

	private static function load_dependencies(): void {
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/Settings.php';
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/Admin/SettingsPage.php';
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/Admin/EditorAssets.php';
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/Forms/CPT.php';
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/Forms/Renderer.php';
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/REST/AdminController.php';
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/REST/FormsController.php';
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/REST/SubmitController.php';
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/Security/Signer.php';
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/Security/RateLimiter.php';
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/HubSpot/Client.php';
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/HubSpot/SchemaMapper.php';
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/Logger.php';
		require_once BBHUBSPOT_FORMS_PLUGIN_DIR . 'src/Spam/Captcha.php';
	}

	private static function register_hooks(): void {
		SettingsPage::register();
		EditorAssets::register();
		CPT::register();
		Renderer::register();
		AdminController::register();
		FormsController::register();
		SubmitController::register();
	}
}

