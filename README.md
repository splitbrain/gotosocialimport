# Mastodon to GotoSocial Importer

This is a crude hack to import your Mastodon posts into GotoSocial. It works by reading your Mastodon archive and creating a new post for each toot directly in the GotoSocial sqlite database.

Current limitations:

  * it only imports toots that do not mention anyone
  * none of those toots will have any replies or favs. as the fediverse is concerned, they are all new toots
  * it only imports "normal" toots, no boosts, no polls
  * it really only works with Mastodon archives. I would love to import my Twitter history as well, but I'm too lazy to implement that right now.
  * it does not import any other info like profile, followers, etc. Those should be set up manually or imported using the built-in GotoSocial features.

This is super hacky and might break whenever GotoSocial changes its database schema.

Yes, it's written in PHP. It was the quickest way to get this done. Ideally this should be part of GotoSocial itself.

It should not have any influence on the Fediverse, since all toots are imported as already federated and no mentions are imported anyway. The imported toots should only be shown to others when they check out your profile. 

## Usage

I recommend running this for a completely new GotoSocial installation only. But in theory it should work with an existing installation as well.

1. Set up a new GotoSocial installation and create the users you want to import to. Be sure to use the sqlite database and local media storage. The script assumes that your sqlite database is named `sqlite.db` and is located in the directory configured as `storage-local-base-path`. This is the default for the docker container.
2. Shut down the GotoSocial server and copy your storage directory from your server to your local machine somewhere, eg. `/tmp/gotosocial-storage` (also make a backup)
3. Export your Mastodon archive (Preferences -> Import abd Export -> Request your archive)
4. Unpack the archive somewhere, eg. `/tmp/mastodon-archive`
5. Clone this repository
6. Install the dependencies using composer `composer install`
7. Run the importer `php import.php /tmp/mastodon-archive/data /tmp/gotosocial-storage @youraccount@yourgotosocial.example.com` this will run in dry-run mode.
8. If the output looks good, run the importer with the `--really` flag to actually import the toots. `php import.php --really /tmp/mastodon-archive/data /tmp/gotosocial-storage @youraccount@yourgotosocial.example.com`
9. Copy the storage directory back to your server and start the GotoSocial server

If anything goes wrong, you can always restore your backup of the storage directory.
