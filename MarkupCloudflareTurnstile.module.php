<?php namespace ProcessWire;

/**
 * Markup Cloudflare Turnstile
 *
 * @copyright 2024 NB Communication Ltd
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 *
 */

class MarkupCloudflareTurnstile extends WireData implements Module, ConfigurableModule {

	public function init() {


        if ($_SERVER['REQUEST_METHOD'] != 'POST') return;
		//Add URL hooks to catch Turnstile functionality from the front end
		wire()->addHook('/turnstile/{route}', function($event) {
		
			switch ($event->arguments('route')) {

				//Handle verification via ajax
				case 'verify':
		
					if ($_SERVER['REQUEST_METHOD'] === 'POST') {
						header('Content-Type: application/json');
					
						$input = json_decode(file_get_contents('php://input'), true);
						$token = $input['cf-turnstile-response'] ?? '';
						$token = wire('sanitizer')->string($token);
					
						if (!$token) {
							return json_encode(['success' => false, 'error' => 'Missing token']);
							exit;
						}

						$response = $this->verifyResponse($token);
					
						if ($response) {
							return json_encode(['success' => true]);
						} else {
							return json_encode(['success' => false]);
						}
					}
					break;
		
				//Provide HTML render files back to front end once Turnstile $token is verified
				case 'render':

					if ($_SERVER['REQUEST_METHOD'] === 'POST') {
						$input = json_decode(file_get_contents('php://input'), true);
						$filePath = $input['filePath'] ?? '';
						$filePath = wire('sanitizer')->string($filePath);
						return $this->wire()->files->render($filePath);
					}
					break;
			}
			
		});

	}

	/**
	 * Render
	 *
	 * @param array $attrs
	 * @param InputfieldForm $form
	 * @return string
	 *
	 */
	public function render($attrs = [], InputfieldForm $form = null) {

		$modules = $this->wire('modules');

		if($attrs instanceof InputfieldForm) {
			$form = $attrs;
			$attrs = [];
		}

		if(!is_array($attrs)) $attrs = [];

		if($modules->isInstalled('MarkupGoogleRecaptcha')) {
			// Piggy back on MarkupGoogleRecaptcha settings
			$markupGoogleRecaptcha = $modules->get('MarkupGoogleRecaptcha');
			$recaptchaInferredAttrs = [
				'data-theme' => $markupGoogleRecaptcha->data_theme ? 'dark' : 'light',
			];
			if($markupGoogleRecaptcha->data_size) {
				$recaptchaInferredAttrs['data-size'] = 'compact';
			}
			if($markupGoogleRecaptcha->data_index) {
				$recaptchaInferredAttrs['data-tabindex'] = $markupGoogleRecaptcha->data_index;
			}
		}

		$attrs = array_merge($recaptchaInferredAttrs ?? [], $attrs, [
			'data-sitekey' => $this->siteKey,
		]);

		if(!isset($attrs['class'])) {
			$attrs['class'] = 'cf-turnstile';
		}

		$attrsStr = '';
		foreach($attrs as $key => $value) {
			if(is_array($value)) $value = implode(' ', $value);
			if(is_object($value)) $value = (string) $value;
			$attrsStr .= is_bool($value) ? " $key" : " $key=\"$value\"";
		}

		$out = "<div $attrsStr></div>";

		if($form) {
			$form->add([
				'type' => 'markup',
				'name' => 'cf-turnstile',
				'value' => $out,
			]);
			return $form;
		}

		return $out;
	}

	/**
	 * Get the Cloudflare Turnstile Client API script
	 *
	 * @param array $params
	 * @return string
	 *
	 */
	public function getScript(array $params = []) {
		$url = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
		if(count($params)) $url .= '?' . http_build_query($params);
		return "<script src=$url defer></script>";
	}

	/**
	 * Verify the response
	 *
	 * @param string $token
	 * @return bool
	 *
	 */
	public function verifyResponse($token = null) {

		$response = ($token) ? $token : $this->wire('input')->post('cf-turnstile-response');
		if(!$response) return false;

		return json_decode($this->wire(new WireHttp())->post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			[
				'secret' => $this->secretKey,
				'response' => $response,
			],
			[
				'use' => 'curl',
			]
		), true)['success'] ?? false;
	}

	/**
	 * Verify the response
	 *
	 * @param string $token
	 * @return bool
	 *
	 */
	public function renderProtectedContent(string $filename) {

		return $this->wire('files')->render('../modules/MarkupCloudflareTurnstile/views/protected_content_scripts.php',[
			'filename' 	=> $filename
		]);		

	}
}
