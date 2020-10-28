<?php

namespace Tests\Feature\Modules\Card\Integrations\Google\Fakes;

class FileFake
{
    public $appProperties = null;
    public $copyRequiresWriterPermission = null;
    public $createdTime = '2018-12-20T23:08:20.325Z';
    public $description = null;
    public $driveId = null;
    public $explicitlyTrashed = null;
    public $exportLinks = [
        'application/rtf'                                                         => 'https://docs.google.com/feeds/download/documents/export/Export?id=1N3yjqu8ZPQiAPFJdiq2Njh6hUGM0c2cBocOKG3q_Eoc&exportFormat=rtf',
        'application/vnd.oasis.opendocument.text'                                 => 'https://docs.google.com/feeds/download/documents/export/Export?id=1N3yjqu8ZPQiAPFJdiq2Njh6hUGM0c2cBocOKG3q_Eoc&exportFormat=odt',
        'text/html'                                                               => 'https://docs.google.com/feeds/download/documents/export/Export?id=1N3yjqu8ZPQiAPFJdiq2Njh6hUGM0c2cBocOKG3q_Eoc&exportFormat=html',
        'application/pdf'                                                         => 'https://docs.google.com/feeds/download/documents/export/Export?id=1N3yjqu8ZPQiAPFJdiq2Njh6hUGM0c2cBocOKG3q_Eoc&exportFormat=pdf',
        'application/epubpublic $zip'                                             => 'https://docs.google.com/feeds/download/documents/export/Export?id=1N3yjqu8ZPQiAPFJdiq2Njh6hUGM0c2cBocOKG3q_Eoc&exportFormat=epub',
        'application/zip'                                                         => 'https://docs.google.com/feeds/download/documents/export/Export?id=1N3yjqu8ZPQiAPFJdiq2Njh6hUGM0c2cBocOKG3q_Eoc&exportFormat=zip',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'https://docs.google.com/feeds/download/documents/export/Export?id=1N3yjqu8ZPQiAPFJdiq2Njh6hUGM0c2cBocOKG3q_Eoc&exportFormat=docx',
        'text/plain'                                                              => 'https://docs.google.com/feeds/download/documents/export/Export?id=1N3yjqu8ZPQiAPFJdiq2Njh6hUGM0c2cBocOKG3q_Eoc&exportFormat=txt',
    ];
    public $fileExtension = 'sketch';
    public $folderColorRgb = '#8f8f8f';
    public $fullFileExtension = 'sketch';
    public $hasAugmentedPermissions = null;
    public $hasThumbnail = true;
    public $headRevisionId = null;
    public $iconLink = 'https://drive-thirdparty.googleusercontent.com/16/type/application/vnd.google-apps.document';
    public $id = '1pfVBaUFC7FnsI_KNQGQHVMnSqblubGGj7hNGqsvHP5k';
    public $isAppAuthorized = false;
    public $kind = 'drive#file';
    public $md5Checksum = '6950bf7e0d73279389156065e17e8941';
    public $mimeType = 'application/vnd.google-apps.document';
    public $modifiedByMe = true;
    public $modifiedByMeTime = '2019-11-26T05:26:37.924Z';
    public $modifiedTime = '2018-12-22T01:12:30.736Z';
    public $name = 'Interview Template v0.1';
    public $originalFilename = 'Interview Template';
    public $ownedByMe = true;
    public $parents = null;
    public $permissionIds = ['13567025846832636056k', '07400812223864051214'];
    public $properties = null;
    public $quotaBytesUsed = '301996721';
    public $shared = false;
    public $sharedWithMeTime = null;
    public $size = '301996721';
    public $spaces = ['drive'];
    public $starred = false;
    public $teamDriveId = null;
    public $thumbnailLink = 'https://docs.google.com/a/trytrig.com/feeds/vt?gd=true&id=1pfVBaUFC7FnsI_KNQGQHVMnSqblubGGj7hNGqsvHP5k&v=14&s=AMedNnoAAAAAXpUOTCtmc4zTbEZ6g0EPywj-ypToA8-U&sz=s220';
    public $thumbnailVersion = 0;
    public $trashed = false;
    public $trashedTime = null;
    public $version = '23';
    public $viewedByMe = true;
    public $viewedByMeTime = '2018-12-22T01:12:30.736Z';
    public $viewersCanCopyContent = true;
    // Export/download? Maybe for files we want this?
    public $webContentLink = 'https://drive.google.com/a/trytrig.com/uc?id=1fwWw4a3aMY6cn-t0yrECjIX8tEERTToj&export=download';
    // View in the browser - this is what we usually want
    public $webViewLink = 'https://docs.google.com/document/d/1pfVBaUFC7FnsI_KNQGQHVMnSqblubGGj7hNGqsvHP5k/edit?usp=drivesdk';
    public $writersCanShare = true;
    public $owners = [];
    public $lastModifyingUser;
    public $capabilities;
    public $permissions;

    public function __construct()
    {
        $this->owners = [new FakeUser()];
        $this->lastModifyingUser = new FakeUser();
        $this->capabilities = new FakeCapabilities();
    }

    public function setPermissions($permissionParams)
    {
        $permissions = [];
        foreach ($permissionParams as $params) {
            $permissions[] = new FakePermissions($params);
        }
        $this->permissions = $permissions;
    }

    public function getNextPageToken(): string
    {
        return 'is_next_page';
    }
}

class FakeUser
{
    public $displayName = 'Brian Jenkins';
    public $emailAddress = 'brian@trytrig.com';
    public $kind = 'drive#user';
    public $me = true;
    public $permissionId = '07400812223864051214';
    public $photoLink = null;
}

class FakeCapabilities
{
    public $canAddChildren = false;
    public $canAddMyDriveParent = false;
    public $canChangeCopyRequiresWriterPermission = true;
    public $canChangeViewersCanCopyContent = true;
    public $canComment = true;
    public $canCopy = true;
    public $canDelete = true;
    public $canDeleteChildren = null;
    public $canDownload = true;
    public $canEdit = true;
    public $canListChildren = false;
    public $canModifyContent = true;
    public $canMoveChildrenOutOfDrive = null;
    public $canMoveChildrenOutOfTeamDrive = null;
    public $canMoveChildrenWithinDrive = false;
    public $canMoveChildrenWithinTeamDrive = null;
    public $canMoveItemIntoTeamDrive = true;
    public $canMoveItemOutOfDrive = true;
    public $canMoveItemOutOfTeamDrive = null;
    public $canMoveItemWithinDrive = true;
    public $canMoveItemWithinTeamDrive = null;
    public $canMoveTeamDriveItem = null;
    public $canReadDrive = null;
    public $canReadRevisions = true;
    public $canReadTeamDrive = null;
    public $canRemoveChildren = false;
    public $canRemoveMyDriveParent = true;
    public $canRename = true;
    public $canShare = true;
    public $canTrash = true;
    public $canTrashChildren = null;
    public $canUntrash = true;
}

class FakePermissions
{
    public bool $allowFileDiscovery;
    public ?bool $deleted;
    public ?string $displayName;
    public ?string $domain;
    public ?string $emailAddress;
    public $expirationTime;
    public string $id;
    public string $kind;
    public ?string $photoLink;
    public $role;
    public $type;

    public function __construct(array $permissions)
    {
        $this->role = $permissions['role'] ?? 'owner';
        $this->domain = $permissions['domain'] ?? null;
        if ('user' === $permissions['type']) {
            $this->allowFileDiscovery = false;
            $this->deleted = false;
            $this->displayName = 'Brian Jenkins';
            $this->domain = null;
            $this->emailAddress = 'brian@trytrig.com';
            $this->expirationTime = null;
            $this->id = '07400812223864051214';
            $this->kind = 'drive#permission';
            $this->photoLink = null;
            $this->type = 'user';
        }
        if ('domain' === $permissions['type']) {
            $this->allowFileDiscovery = false;
            $this->deleted = null;
            $this->displayName = 'Trig';
            $this->emailAddress = null;
            $this->expirationTime = null;
            $this->id = '13567025846832636056k';
            $this->kind = 'drive#permission';
            $this->photoLink = null;
            $this->type = 'domain';
        }
        if ('anyone' === $permissions['type']) {
            $this->allowFileDiscovery = true;
            $this->deleted = null;
            $this->displayName = null;
            $this->domain = null;
            $this->emailAddress = null;
            $this->expirationTime = null;
            $this->id = 'anyone';
            $this->kind = 'drive#permission';
            $this->photoLink = null;
            $this->type = 'anyone';
        }
    }
}
