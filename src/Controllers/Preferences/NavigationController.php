<?php

declare(strict_types=1);

namespace PhpMyAdmin\Controllers\Preferences;

use PhpMyAdmin\Config;
use PhpMyAdmin\Config\ConfigFile;
use PhpMyAdmin\Config\Forms\User\NaviForm;
use PhpMyAdmin\ConfigStorage\Relation;
use PhpMyAdmin\Controllers\InvocableController;
use PhpMyAdmin\Current;
use PhpMyAdmin\Http\Response;
use PhpMyAdmin\Http\ServerRequest;
use PhpMyAdmin\Message;
use PhpMyAdmin\Navigation\Navigation;
use PhpMyAdmin\ResponseRenderer;
use PhpMyAdmin\Routing\Route;
use PhpMyAdmin\Theme\ThemeManager;
use PhpMyAdmin\TwoFactor;
use PhpMyAdmin\Url;
use PhpMyAdmin\UserPreferences;

use function ltrim;

#[Route('/preferences/navigation', ['GET', 'POST'])]
final readonly class NavigationController implements InvocableController
{
    public function __construct(
        private ResponseRenderer $response,
        private UserPreferences $userPreferences,
        private Relation $relation,
        private Config $config,
        private ThemeManager $themeManager,
    ) {
    }

    public function __invoke(ServerRequest $request): Response
    {
        $configFile = new ConfigFile($this->config->baseSettings);
        $this->userPreferences->pageInit($configFile);

        $formDisplay = new NaviForm($configFile, 1);

        if ($request->hasBodyParam('revert')) {
            // revert erroneous fields to their default values
            $formDisplay->fixErrors();

            return $this->response->redirectToRoute('/preferences/navigation');
        }

        $result = null;
        if ($formDisplay->process(false) && ! $formDisplay->hasErrors()) {
            // Load 2FA settings
            $twoFactor = new TwoFactor($this->config->selectedServer['user']);
            // save settings
            $result = $this->userPreferences->save($configFile->getConfigArray());
            // save back the 2FA setting only
            $twoFactor->save();
            if ($result === true) {
                // reload config
                $this->config->loadUserPreferences($this->themeManager);
                $hash = ltrim($request->getParsedBodyParamAsString('tab_hash'), '#');

                return $this->userPreferences->redirect('index.php?route=/preferences/navigation', null, $hash);
            }
        }

        $relationParameters = $this->relation->getRelationParameters();

        $this->response->render('preferences/header', [
            'route' => $request->getRoute(),
            'is_saved' => $request->hasQueryParam('saved'),
            'has_config_storage' => $relationParameters->userPreferencesFeature !== null,
        ]);

        $formErrors = $formDisplay->displayErrors();

        $this->response->render('preferences/forms/main', [
            'error' => $result instanceof Message ? $result->getDisplay() : '',
            'has_errors' => $formDisplay->hasErrors(),
            'errors' => $formErrors,
            'form' => $formDisplay->getDisplay(
                true,
                Url::getFromRoute('/preferences/navigation'),
                ['server' => Current::$server],
            ),
        ]);

        if ($request->isAjax()) {
            $this->response->addJSON('disableNaviSettings', true);

            return $this->response->response();
        }

        Navigation::$isSettingsEnabled = false;

        return $this->response->response();
    }
}
