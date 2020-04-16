# Things you have to run
Things you have to install:
`brew install mysql@8.0`
`brew install php@7.4.3`
`brew install redis@5.0.8`

`npm run fresh` - Gets oauth stuff - and runs all migrations fresh
`php artisan storage:link` - will make `/storage/` accessible. Needed for images to work locally
`php artisan horizon:install` 


# DB Design

`cards`
(id, user_id, card_type_id, title, description, image actual_created_at actual_modified_at created_at modified_at)

`card_documents`
(id, card_id, content)

`card_favorites`
(id, card_id, user_id)

`card_files`
(id, card_id, url)

`card_links`
(id, card_id, link)

`card_types` 
(id, name)

`decks`
(id, user_id, title, description, image)

`deck_cards`
(id, deck_id, card_id)

`deck_followers`
(id, deck_id, user_id)

`organizations`
(id, name)

`organizations_users`
(id, user_id, organization_id)

`permissions` - **edit** or **view** 
(id, name)

`roles` - **admin**, **manager**, or **member** 
(id, name)

`share_settings`
(id, shareable_type, shareable_id, link)

`share_setting_links`
(id, permission_id, share_link_type_id)

`share_setting_link_types` - **has_link** or **public**
(id, name)

`share_setting_people` this will be unused but we need to save if in the future we allow for sharing with individuals
(id, share_setting_id, email)

`share_setting_teams`
(id, share_setting_id, team_id, permission_id)

`share_setting_users`
(id, share_setting_id, user_id, permission_id)

`teams` 
(id, organization_id, name)

`teams_users`
(id, team_id, user_id)

`users`
(id, role_id, first_name, last_name, email)

`views`
(id, viewable_type, viewable_id, created_at)
