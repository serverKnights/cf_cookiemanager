<?php

declare(strict_types=1);

namespace CodingFreaks\CfCookiemanager\Domain\Repository;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;


/**
 * This file is part of the "Coding Freaks Cookie Manager" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * (c) 2022 Florian Eibisberger, CodingFreaks
 */

/**
 * The repository for CookieFrontends
 */
class CookieFrontendRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{

    protected $cookieServiceRepository = null;
    protected $cookieCartegoriesRepository = null;
    protected $cookieFrontendRepository = null;
    protected $variablesRepository = null;

    /**
     * @param \CodingFreaks\CfCookiemanager\Domain\Repository\CookieServiceRepository $cookieServiceRepository
     */
    public function injectCookieServiceRepository(\CodingFreaks\CfCookiemanager\Domain\Repository\CookieServiceRepository $cookieServiceRepository)
    {
        $this->cookieServiceRepository = $cookieServiceRepository;
    }

    /**
     * @param \CodingFreaks\CfCookiemanager\Domain\Repository\CookieCartegoriesRepository $cookieCartegoriesRepository
     */
    public function injectCookieCartegoriesRepository(\CodingFreaks\CfCookiemanager\Domain\Repository\CookieCartegoriesRepository $cookieCartegoriesRepository)
    {
        $this->cookieCartegoriesRepository = $cookieCartegoriesRepository;
    }

    /**
     * @param \CodingFreaks\CfCookiemanager\Domain\Repository\CookieFrontendRepository $cookieFrontendRepository
     */
    public function injectCookieFrontendRepository(\CodingFreaks\CfCookiemanager\Domain\Repository\CookieFrontendRepository $cookieFrontendRepository)
    {
        $this->cookieFrontendRepository = $cookieFrontendRepository;
    }

    /**
     * @param \CodingFreaks\CfCookiemanager\Domain\Repository\VariablesRepository $variablesRepository
     */
    public function injectVariablesRepository(\CodingFreaks\CfCookiemanager\Domain\Repository\VariablesRepository $variablesRepository)
    {
        $this->variablesRepository = $variablesRepository;
    }

    /**
     * Get frontend records by sys_language_uid and storage page IDs as array.
     *
     * @param int $langUid The sys_language_uid to filter records. Default is 0.
     * @param array $storage An array of storage page IDs. Default is [1].
     * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface The result of the query execution.
     */
    public function getFrontendBySysLanguage($langUid = 0,$storage=[1]){
        //
        $query = $this->createQuery();
        $query->getQuerySettings()->setLanguageUid($langUid)->setStoragePageIds($storage);
        $query->setOrderings(array("crdate" => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING))->setLimit(1);
        //$queryParser = $this->objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\Storage\Typo3DbQueryParser::class);
        //echo $queryParser->convertQueryToDoctrineQueryBuilder($query)->getSQL();
        return $query->execute();
    }


    /**
     * Get frontend records by language iso code and storage page IDs array.
     *
     * @param string $code The language code to filter records.
     * @param array $storage An array of storage page IDs. Default is [1].
     * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface The result of the query execution.
     */
    public function getFrontendByLangCode($code,$storage=[1])
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setStoragePageIds($storage)->setRespectSysLanguage(false);
        $query->matching($query->logicalAnd($query->equals('identifier', $code)));
        $query->setOrderings(array("crdate" => \TYPO3\CMS\Extbase\Persistence\QueryInterface::ORDER_ASCENDING))->setLimit(1);
        //$queryParser = $this->objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\Storage\Typo3DbQueryParser::class);
        //echo $queryParser->convertQueryToDoctrineQueryBuilder($query)->getSQL();
        return $query->execute();
    }

    /**
     * Get all frontend records from the specified storage page IDs.
     *
     * @param array $storage An array of storage page IDs. Default is [1].
     * @return \TYPO3\CMS\Extbase\Persistence\QueryResultInterface The result of the query execution.
     */
    public function getAllFrontendsFromStorage($storage=[1]){
        $query = $this->createQuery();
        $query->getQuerySettings()->setStoragePageIds($storage)->setRespectSysLanguage(false);
        return $query->execute();
    }

    /**
     * Get all frontend records from the API for the specified language.
     *
     * @param string $lang The language code for filtering frontend records from the API.
     * @return array An array of frontend records obtained from the API or an empty array if the API endpoint is not configured or encounters an error.
     */
    public function getAllFrontendsFromAPI($lang)
    {
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('cf_cookiemanager');
        if(!empty($extensionConfiguration["endPoint"])){
            $json = file_get_contents($extensionConfiguration["endPoint"]."frontends/".$lang);
            $frontends = json_decode($json, true);
            return $frontends;
        }
        return [];
    }

    /**
     * Insert frontend records from the API into the database for specified languages.
     *
     * This function fetches frontend data from an external API for each language specified in the $lang array.
     * It inserts the retrieved frontend into the database as new records if they do not already exist.
     * If the frontend already exist, the function checks if translations exist for the category in the specified
     * language and inserts translations if necessary.
     *
     * @param array $lang An array containing language configurations for inserting frontend records.
     * @return void
     */
    public function insertFromAPI($lang){

        foreach ($lang as $lang_config){
            if(empty($lang_config)){
                die("Invalid Typo3 Site Configuration");
            }

            foreach ($lang_config as $localeString => $lang){


                $frontends = $this->getAllFrontendsFromAPI($lang["langCode"]);

                foreach ($frontends as $frontend) {
                    $frontendModel = new \CodingFreaks\CfCookiemanager\Domain\Model\CookieFrontend();
                    $frontendModel->setPid($lang["rootSite"]);
                    $frontendModel->setName($frontend["name"]);
                    $frontendModel->setIdentifier($localeString);
                    $frontendModel->setTitleConsentModal($frontend["title_consent_modal"] ?? "");
                    $frontendModel->setEnabled("1");
                    $frontendModel->setDescriptionConsentModal($frontend["description_consent_modal"] ?? "");
                    $frontendModel->setPrimaryBtnTextConsentModal($frontend["primary_btn_text_consent_modal"] ?? "");
                    $frontendModel->setSecondaryBtnTextConsentModal($frontend["secondary_btn_text_consent_modal"] ?? "");
                    $frontendModel->setTertiaryBtnTextConsentModal($frontend["tertiary_btn_text_consent_modal"] ?? "");
                    $frontendModel->setPrimaryBtnRoleConsentModal($frontend["primary_btn_role_consent_modal"] ?? "accept_all");
                    $frontendModel->setSecondaryBtnRoleConsentModal($frontend["secondary_btn_role_consent_modal"] ?? "accept_necessary");
                    $frontendModel->setTertiaryBtnRoleConsentModal($frontend["tertiary_btn_role_consent_modal"] ?? "display_none");
                    $frontendModel->setLayoutConsentModal("cloud");
                    $frontendModel->setTransitionConsentModal("slide");
                    $frontendModel->setPositionConsentModal("bottom center");

                    $frontendModel->setTitleSettings($frontend["title_settings"] ?? "");
                    $frontendModel->setAcceptAllBtnSettings($frontend["accept_all_btn_settings"] ?? "");
                    $frontendModel->setCloseBtnSettings($frontend["close_btn_settings"] ?? "");
                    $frontendModel->setSaveBtnSettings($frontend["save_btn_settings"] ?? "");
                    $frontendModel->setRejectAllBtnSettings($frontend["reject_all_btn_settings"] ?? "");
                    $frontendModel->setCol1HeaderSettings($frontend["col1_header_settings"] ?? "");
                    $frontendModel->setCol2HeaderSettings($frontend["col2_header_settings"] ?? "");
                    $frontendModel->setCol3HeaderSettings($frontend["col3_header_settings"] ?? "");
                    $frontendModel->setBlocksTitle($frontend["blocks_title"] ?? "");
                    $frontendModel->setBlocksDescription($frontend["blocks_description"] ?? "");
                    $frontendModel->setCustomButtonHtml($frontend["custom_button_html"] ?? "");
                    $frontendModel->setLayoutSettings("box");
                    $frontendModel->setTransitionSettings("slide");


                    if(!empty($frontend["custombutton"])){
                        $frontendModel->setCustombutton($frontend["custombutton"]);
                    }

                    //var_dump($lang["rootSite"]);
                    $frontendDB = $this->getFrontendBySysLanguage(0,[$lang["rootSite"]]);
                    if (count($frontendDB) == 0) {
                        $this->add($frontendModel);
                        $this->persistenceManager->persistAll();
                    }


                    if($lang["language"]["languageId"] != 0){
                        $frontendDB = $this->getFrontendBySysLanguage(0,[$lang["rootSite"]]); // $lang_config["languageId"]
                        $allreadyTranslated = $this->getFrontendBySysLanguage($lang["language"]["languageId"],[$lang["rootSite"]]);
                        if (count($allreadyTranslated) == 0) {
                            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_cfcookiemanager_domain_model_cookiefrontend');
                            $queryBuilder->insert('tx_cfcookiemanager_domain_model_cookiefrontend')->values([
                                'pid' => $lang["rootSite"],
                                'sys_language_uid' => $lang["language"]["languageId"],
                                'l10n_parent' => (int)$frontendDB[0]->getUid(),
                                'name' =>$frontendModel->getName(),
                                'identifier' =>$frontendModel->getIdentifier(),
                                'title_consent_modal' =>$frontendModel->getTitleConsentModal(),
                                'description_consent_modal' =>$frontendModel->getDescriptionConsentModal(),
                                'primary_btn_text_consent_modal' =>$frontendModel->getPrimaryBtnTextConsentModal(),
                                'secondary_btn_text_consent_modal' =>$frontendModel->getSecondaryBtnTextConsentModal(),
                                'tertiary_btn_text_consent_modal' =>$frontendModel->getTertiaryBtnTextConsentModal(),
                                'primary_btn_role_consent_modal' =>$frontendModel->getPrimaryBtnRoleConsentModal(),
                                'secondary_btn_role_consent_modal' =>$frontendModel->getSecondaryBtnRoleConsentModal(),
                                'tertiary_btn_role_consent_modal' =>$frontendModel->getTertiaryBtnRoleConsentModal(),
                                'title_settings' =>$frontendModel->getTitleSettings(),
                                'accept_all_btn_settings' =>$frontendModel->getAcceptAllBtnSettings(),
                                'close_btn_settings' =>$frontendModel->getCloseBtnSettings(),
                                'save_btn_settings' =>$frontendModel->getSaveBtnSettings(),
                                'reject_all_btn_settings' =>$frontendModel->getRejectAllBtnSettings(),
                                'col1_header_settings' =>$frontendModel->getCol1HeaderSettings(),
                                'col2_header_settings' =>$frontendModel->getCol2HeaderSettings(),
                                'col3_header_settings' =>$frontendModel->getCol3HeaderSettings(),
                                'blocks_title' =>$frontendModel->getBlocksTitle(),
                                'blocks_description' =>$frontendModel->getBlocksDescription(),
                                'custombutton' =>(int)$frontendModel->getCustombutton(),
                                'custom_button_html' =>$frontendModel->getCustomButtonHtml(),
                            ])
                                ->execute();
                        }
                    }

                    $this->persistenceManager->persistAll();
                }
            }
        }
    }

    /**
     * Generate a JSON representation of frontend settings, categories, and cookies for the specified language.
     *
     * @param int $langId The sys_language_uid for the language.
     * @param array $storages An array of storage page IDs to filter frontend settings.
     * @return string The JSON representation of frontend settings, categories, and cookies.
     */
    public function getLaguage($langId,$storages)
    {
        //$frontendSettings = $this->cookieFrontendRepository->getFrontendBySysLanguage($langId,$storages);
        $frontendSettings = $this->cookieFrontendRepository->getAllFrontendsFromStorage($storages);
        if (empty($frontendSettings)) {
            die("Wrong Cookie Language Configuration");
        }

        $cObj = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer');
        $lang = [];
        foreach ($frontendSettings as $frontendSetting){
            $lang[$frontendSetting->_getProperty("_languageUid")] = [
                "consent_modal" => [
                    "title" => $frontendSetting->getTitleConsentModal(),
                    "description" => $cObj->parseFunc($frontendSetting->getDescriptionConsentModal(), [], '< ' . 'lib.parseFunc_RTE')."<br\><br\>{{revision_message}}",
                    "primary_btn" => [
                        "text" => $frontendSetting->getPrimaryBtnTextConsentModal(),
                        "role" => $frontendSetting->getPrimaryBtnRoleConsentModal()
                    ],
                    "secondary_btn" => [
                        "text" => $frontendSetting->getSecondaryBtnTextConsentModal(),
                        "role" => $frontendSetting->getSecondaryBtnRoleConsentModal()
                    ],
                    "tertiary_btn" => [
                        "text" => $frontendSetting->getTertiaryBtnTextConsentModal(),
                        "role" => $frontendSetting->getTertiaryBtnRoleConsentModal(),
                    ],
                    "revision_message" => $cObj->parseFunc($frontendSetting->getRevisionText(),[],'< ' . 'lib.parseFunc_RTE'),
                    "impress_link" => $cObj->typoLink($frontendSetting->getImpressText(),['parameter'=> $frontendSetting->getImpressLink(),'ATagParams'=> 'class="cc-link"']),
                    "data_policy_link" => $cObj->typoLink($frontendSetting->getDataPolicyText(),['parameter'=> $frontendSetting->getDataPolicyLink(),'ATagParams'=> 'class="cc-link"']),

                ],
                "settings_modal" => [
                    "title" => $frontendSetting->getTitleSettings(),
                    "save_settings_btn" => $frontendSetting->getSaveBtnSettings(),
                    "accept_all_btn" => $frontendSetting->getAcceptAllBtnSettings(),
                    "reject_all_btn" => $frontendSetting->getRejectAllBtnSettings(),
                    'close_btn_label' => $frontendSetting->getCloseBtnSettings(),
                    'cookie_table_headers' => [
                        ["col1" => $frontendSetting->getCol1HeaderSettings()],
                        ["col2" => $frontendSetting->getCol2HeaderSettings()],
                     //   ["col3" => $frontendSetting->getCol3HeaderSettings()], //TODO Info Icon and Popup cookie Detail Information.
                    ],
                    'blocks' => [["title" => $frontendSetting->getBlocksTitle(), "description" => $cObj->parseFunc($frontendSetting->getBlocksDescription(),[],'< ' . 'lib.parseFunc_RTE')]]
                ]
            ];

            $categories = $this->cookieCartegoriesRepository->getAllCategories($storages,$frontendSetting->_getProperty("_languageUid"));

            foreach ($categories as $category) {
                if(count($category->getCookieServices()) <= 0){
                    if($category->getIsRequired() === FALSE){
                        //Ignore all Missconfigured Services expect required
                        continue;
                    }
                }

                foreach ($category->getCookieServices() as $service) {
                    $cookies = [];
                    foreach ($service->getCookie() as $cookie) {
                        $uri= GeneralUtility::makeInstance(
                            \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::class
                        )->typoLink("Provider",['parameter'=>$service->getDsgvoLink()]);
                        $cookies[] = [
                            "col1" => $cookie->getName(),
                            "col2" => $uri,
                            //"col3" => "(I)", //TODO Info Icon and Popup cookie Detail Information.
                            "is_regex" => $cookie->getIsRegex(),
                        ];
                    }
                    $lang[$service->_getProperty("_languageUid")]["settings_modal"]["blocks"][] = [
                        'title' => $service->getName(),
                        'description' => $service->getDescription(),
                        'toggle' => [
                            'value' => $service->getIdentifier(),
                            'readonly' => $category->getIsRequired(),
                            'enabled' => $category->getIsRequired()
                        ],
                        "cookie_table" => $cookies,
                        "category" => $category->getIdentifier()
                    ];
                }

                $lang[$frontendSetting->_getProperty("_languageUid")]["settings_modal"]["categories"][] = [
                    'title' => $category->getTitle(),
                    'description' => $category->getDescription(),
                    'toggle' => [
                        'value' => $category->getIdentifier(),
                        'readonly' => $category->getIsRequired(),
                        'enabled' => $category->getIsRequired()
                    ],
                    "category" => $category->getIdentifier()
                ];
            }
        }

        $lang = json_encode($lang);
        return $lang;
    }

    /**
     * Generate the configuration for the IframeManager with the specified storages.
     *
     * @param array $storages An array of storage page IDs to retrieve categories and cookie services.
     * @return string The IframeManager configuration as a JavaScript string, or an empty string if the configuration is not available.
     */
    public function getIframeManager($storages)
    {
        $managerConfig = ["currLang" => "en"];
        $categories = $this->cookieCartegoriesRepository->getAllCategories($storages);

        foreach ($categories as $category) {
            foreach ($category->getCookieServices() as $cookie) {
                $managerConfig["services"][$cookie->getIdentifier()] = [
                    "embedUrl" => "{data-id}",
                    "iframe" => ["allow" => " accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen; "],
                    "cookie" => [
                        "name" => $cookie->getIdentifier(),
                        "path" => "/"
                    ],
                    "languages" => [
                        "en" => [
                            "notice" => $cookie->getIframeNotice(),
                            "loadBtn" => $cookie->getIframeLoadBtn(),
                            "loadAllBtn" => $cookie->getIframeLoadAllBtn()
                        ]
                    ],
                ];
            }
        }
        $json_string = json_encode($managerConfig, JSON_FORCE_OBJECT);
        $json_string = preg_replace('/"(\\w+)":/', "\$1:", $json_string);

        if($json_string === '{currLang:"en"}'){
            //IframeManager is not Configured
            return "";
        }

        $config = " var iframemanagerconfig = {$json_string};";
        foreach ($categories as $category) {
            foreach ($category->getCookieServices() as $service) {
                $iframeThumbUrl = "";
                if (!empty($service->getIframeThumbnailUrl())) {
                    $iframeThumbUrl = $service->getIframeThumbnailUrl();
                    if (str_contains($iframeThumbUrl, "function")) {
                        //is JS Function
                        $config .= "iframemanagerconfig.services." . $service->getIdentifier() . ".thumbnailUrl = " . $iframeThumbUrl.";";
                    }
                }

                if (!empty($service->getIframeEmbedUrl())) {
                    $iframeEmbedUrl = $service->getIframeEmbedUrl();
                    if (str_contains($iframeEmbedUrl, "function")) {
                        //is JS Function
                        $config .= "iframemanagerconfig.services." . $service->getIdentifier() . ".embedUrl = " . $iframeEmbedUrl.";";
                    }
                }

            }
        }

        $config .= "manager.run(iframemanagerconfig);";
        return $config;
    }

    /**
     * This function builds the basis configuration for the CookieFrontend based on the provided language ID and extension configurations.
     *
     * @param int $langId The sys_language_uid for the language.
     * @return string The basis configuration as a JSON representation, or an empty string if the frontend settings are not available for the specified language.
     */
    public function basisconfig($langId)
    {
        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('cf_cookiemanager');
        if(empty($extensionConfiguration["revisionVersion"])){
            $extensionConfiguration["revisionVersion"] = 1;
        }
        if(empty($extensionConfiguration["cookiePath"])){
            $extensionConfiguration["cookiePath"] = "/";
        }
        if(empty($extensionConfiguration["cookieExpiration"])){
            $extensionConfiguration["cookieExpiration"] = 365;
        }
        $frontendSettings = $this->cookieFrontendRepository->getFrontendBySysLanguage($langId);
        $config = [];
        if(!empty($frontendSettings[0])){
            $config = [
                "current_lang" => "$langId",
                "autoclear_cookies" => true,
                "cookie_name" => "cf_cookie",
                "revision" => intval($extensionConfiguration["revisionVersion"]),
                "cookie_expiration" => intval($extensionConfiguration["cookieExpiration"]),
                "cookie_path" => $extensionConfiguration["cookiePath"],
                "hide_from_bots" => intval($extensionConfiguration["hideFromBots"]),
                "page_scripts" => true,
                "force_consent" => true,
                "gui_options" => [
                    "consent_modal" => [
                        "layout" => $frontendSettings[0]->getLayoutConsentModal(), // box,cloud,bar
                        "position" => $frontendSettings[0]->getPositionConsentModal(), // bottom,middle,top + left,right,center = "bottom center"
                        "transition" => $frontendSettings[0]->getTransitionConsentModal(),
                    ],
                    "settings_modal" => [
                        "layout" =>  $frontendSettings[0]->getLayoutSettings(),
                        // box,bar
                        "position" => $frontendSettings[0]->getPositionSettings(),
                        // right,left (available only if bar layout selected)
                        "transition" => $frontendSettings[0]->getTransitionSettings(),
                    ]
                ]
            ];
        }

        $configArrayJS = json_encode($config, JSON_FORCE_OBJECT);
        $json_string = preg_replace('/"(\\w+)":/', "\$1:", $configArrayJS);
        return $json_string;
    }

    /**
     * Add external service scripts from Database to the AssetCollector for inclusion on the frontend.
     *
     *
     * @return bool Always returns true after adding the scripts to the AssetCollector.
     */
    public function addExternalServiceScripts()
    {
        $categories = $this->cookieCartegoriesRepository->findAll();
        foreach ($categories as $category) {
            $services = $category->getCookieServices();
            if (!empty($services)) {
                foreach ($services as $service) {
                    $allExternalScripts = $service->getExternalScripts();
                    $allVariables = $service->getVariablePriovider();
                    if (!empty($allExternalScripts)) {
                        foreach ($allExternalScripts as $externalScript) {
                            $string = $this->variablesRepository->replaceVariable($externalScript->getLink(), $allVariables);
                            GeneralUtility::makeInstance(AssetCollector::class)->addJavaScript(
                                $externalScript->getName(),
                                $string,
                                [
                                    'type' => 'text/plain',
                                    'external' => 1,
                                    "async" => $externalScript->getAsync(),
                                    "data-service" => $service->getIdentifier()
                                ]
                            );
                        }
                        if (!empty($service->getOptInCode())) {
                            $string = $this->variablesRepository->replaceVariable($service->getOptInCode(), $allVariables);
                            $identifierFrontend = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(32))), 0, 32);

                            // 32 characters, without /=+;
                            GeneralUtility::makeInstance(AssetCollector::class)->addInlineJavaScript(
                                $identifierFrontend,
                                $string,
                                [
                                    'type' => 'text/plain',
                                    'external' => 1,
                                    "async" => 0,
                                    "defer" => "defer",
                                    "data-service" => $service->getIdentifier()
                                ]
                            );
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * Retrieve the contents of the Tracking.js file and return it as a string.
     *
     * @return string The contents of the Tracking.js file as a string, or null if the file cannot be read.
     */
    public function addTrackingJS(){
        $jsCode = file_get_contents(GeneralUtility::getFileAbsFileName('EXT:cf_cookiemanager/Resources/Public/JavaScript/Tracking.js'));
        return $jsCode;
    }


    /**
     * Generate the service opt-in/opt-out configuration for the CookieServices.
     *
     *
     * @param bool $output Determines whether to output the opt-in configuration or return an empty string.
     * @param array $storages The storage page IDs to retrieve the service opt-in configuration for.
     * @return string The full service opt-in configuration as a JavaScript code string, or an empty string if $output is false or no categories are available.
     */
    public function getServiceOptInConfiguration($output,$storages)
    {
        if ($output == false) {
            return "";
        }
        $categories = $this->cookieCartegoriesRepository->getAllCategories($storages);
        $fullConfig = "";

        foreach ($categories as $category) {
            $services = $category->getCookieServices();
            if (!empty($services)) {
                foreach ($services as $service) {
                    $allVariables = $service->getVariablePriovider();
                    $fullConfig .= "\n  if(!cc.allowedCategory('" . $service->getIdentifier() . "')){\n 
                     manager.rejectService('" . $service->getIdentifier() . "');\n                       
                       ". $this->variablesRepository->replaceVariable($service->getOptOutCode(), $allVariables) ."
                     }else{\n               
                         manager.acceptService('" . $service->getIdentifier() . "'); \n 
                         ". $this->variablesRepository->replaceVariable($service->getOptInCode(), $allVariables) ."
                    }";
                }
            }
        }
        return $fullConfig;
    }

    /**
     * Generate the final cookie consent configuration and return it as JavaScript code.
     *
     *
     * @param int $langId The language ID to use for the cookie consent configuration.
     * @param bool $inline Determines whether to output the cookie consent configuration as inline JavaScript code.
     * @param array $storages The storage page IDs to retrieve the cookie consent configuration for.
     * @return string The rendered cookie consent configuration as JavaScript code, either as a standalone script or an inline script based on the $inline setting.
     */
    public function getRenderedConfig($langId, $inline = false,$storages = [1])
    {

        $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('cf_cookiemanager');

        $this->addExternalServiceScripts();
        $config = "var cc;";

        if(file_exists(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::resolvePackagePath($extensionConfiguration["CF_CONSENTMODAL_TEMPLATE"]))){
            $config .= "var CF_CONSENTMODAL_TEMPLATE = `".file_get_contents(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::resolvePackagePath($extensionConfiguration["CF_CONSENTMODAL_TEMPLATE"]))."`;";
        }
        if(file_exists(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::resolvePackagePath($extensionConfiguration["CF_SETTINGSMODAL_TEMPLATE"]))){
            $config .= "var CF_SETTINGSMODAL_TEMPLATE = `".file_get_contents(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::resolvePackagePath($extensionConfiguration["CF_SETTINGSMODAL_TEMPLATE"]))."`;";
        }
        if(file_exists(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::resolvePackagePath($extensionConfiguration["CF_SETTINGSMODAL_CATEGORY_TEMPLATE"]))){
            $config .= "var CF_SETTINGSMODAL_CATEGORY_TEMPLATE = `".file_get_contents(\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::resolvePackagePath($extensionConfiguration["CF_SETTINGSMODAL_CATEGORY_TEMPLATE"]))."`;";
        }

        $config .= "var manager;";
        $config .= "var cf_cookieconfig = " . $this->basisconfig($langId) . ";";
        $config .= "cf_cookieconfig.languages = " . $this->getLaguage($langId,$storages) . ";";



        $iframeManager = "manager = iframemanager();  " . $this->getIframeManager($storages) . "  ";
        $config .= $iframeManager;
        $config .= "cf_cookieconfig.onAccept =  function(){ " . $this->getServiceOptInConfiguration(true,$storages) . "};";

        if(!empty($extensionConfiguration["trackingEnabled"]) && intval($extensionConfiguration["trackingEnabled"]) == 1){
            $config .= "cf_cookieconfig.onFirstAction =  function(user_preferences, cookie){ ". $this->addTrackingJS() . "};"; //Tracking blacklists the complete cookie manager in Brave or good adblockers, find a better solution for this
        }

        //   $config .= "cf_cookieconfig.onFirstAction = '';";
        $config .= "cf_cookieconfig.onChange = function(cookie, changed_preferences){  " . $this->getServiceOptInConfiguration(true,$storages) . " };";
        $config .= "cc = initCookieConsent();";
        $config .= "cc.run(cf_cookieconfig);";
        $code = $config;



        if ($inline) {
            $code = "window.addEventListener('load', function() {   " . $config . "  }, false);";
        }

        return $code;
    }
}
