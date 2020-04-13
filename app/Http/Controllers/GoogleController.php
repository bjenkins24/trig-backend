<?php

namespace App\Http\Controllers;

use Google_Client as GoogleClient;
use Google_Service_Drive as GoogleServiceDrive;
use App\Modules\UserOauthIntegration\UserAuthIntegrationService;
use App\Modules\UserOauthIntegration\Integrations\Google as GoogleIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class GoogleController extends Controller
{
    /**
     * @var UserOauthIntegrationService
     */
    private UserOauthIntegrationService $userOauthIntegration;

    /**
     * @var GoogleIntegration
     */
    private GoogleIntegration $googleIntegration;

    /**
     * Construct the controller
     *
     * @param UserOauthIntegrationService $userOauthIntegration
     */
    public function __construct(
        UserOauthIntegrationService $userOauthIntegration,
    ) {
        $this->userOauthIntegration = $userOauthIntegration;
    }

    public function google(Request $request) {
        $user = User::where('id', '1')->first();
        $this->userOauthIntegration->createConnection(
            $user, 
            $this->googleIntegration, 
            $request->get('auth_token')
        );
    }
        // $client->setAccessToken('ya29.a0Ae4lvC1Lynm-5hESzXjD-tW2KPx_Xlf3NaG9lougnl5_m6UccuoqbtotoxT4FmcAUBKTDNAYT6KmdEcKV7gnhpx161zkOOj2rBs09zBEqfQeQgOC5RqVneJd3A9Iv9i2LtlXO4UDBKfb-xs3QxTgj2b7Euhj1ohZq2QA');

        // $service = new GoogleServiceDrive($client);
       
        // // Print the names and IDs for up to 10 files.
        // $optParams = array(
        //     'pageSize' => 100,
        //     'fields' => 'nextPageToken, files(id, name, createdTime, modifiedTime, webViewLink, thumbnailLink, starred, iconLink, viewedByMeTime, mimeType)'
        // );
        // $results = $service->files->listFiles($optParams);
        // // dd($results->nextPageToken)
        
        
        // $result = [];
        // if (count($results->getFiles()) !== 0) {
        //     foreach ($results->getFiles() as $file) {
        //         $result[$file->getId()] = [
        //             'name' => $file->name,
        //             'created' => $file->createdTime,
        //             'updated' => $file->modifiedTime,
        //             'link' => $file->webViewLink,
        //             'image' => $file->thumbnailLink,
        //             'starred' => $file->starred,
        //             'typeIcon' => $file->iconLink,
        //             'viewedByMeTime' => $file->viewedByMeTime,
        //             'mimeType' => $file->mimeType,
        //             // 'permissions' => $file->permissions
        //         ];
        //     }
        // } 
        // return response()->json(['data' => $result]);
    }
}