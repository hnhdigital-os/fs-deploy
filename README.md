```
  ________.__  __ ________                .__                
 /  _____/|__|/  |\______ \   ____ ______ |  |   ____ ___.__.
/   \  ___|  \   __\    |  \_/ __ \\____ \|  |  /  _ <   |  |
\    \_\  \  ||  | |    `   \  ___/|  |_> >  |_(  <_> )___  |
 \______  /__||__|/_______  /\___  >   __/|____/\____// ____|
        \/                \/     \/|__|               \/     
```
Provides the ability to create deployment configuration that can be used to deploy files to a remote (or local) filesystem.

[![Github Releases](https://img.shields.io/github/downloads/hnhdigital-os/git-deploy/latest/total.svg)](https://github.com/hnhdigital-os/git-deploy) [![GitHub release](https://img.shields.io/github/release/hnhdigital-os/git-deploy.svg)]()

[![StyleCI](https://styleci.io/repos/96600391/shield?branch=master)](https://styleci.io/repos/96600391) [![Issue Count](https://codeclimate.com/github/hnhdigital-os/git-deploy/badges/issue_count.svg)](https://codeclimate.com/github/hnhdigital-os/git-deploy) [![Code Climate](https://codeclimate.com/github/hnhdigital-os/git-deploy/badges/gpa.svg)](https://codeclimate.com/github/hnhdigital-os/git-deploy) 

This package has been developed by H&H|Digital, an Australian botique developer. Visit us at [hnh.digital](http://hnh.digital).


## Install

`$ bash <(curl -H -s https://hnhdigital-os.github.io/git-deploy/install)`

`$ mv git-deploy /usr/local/bin/git-deploy`

## Usage

GitDepoy created a .gitdeploy configuration file in the root directory of your project. It will automatically add this to your .gitignore file.

Simply run git-deploy in your project root folder:

`$ git-deploy config`

Follow the prompts and then you can:

`$ git-deploy deploy`

GitDeploy comes with the ability to self-update:

`$ git-deploy self-update`

## Available remote filesystems

* AWS S3

## Missing

GitDeploy uses the [Flysystem](https://github.com/thephpleague?utf8=✓&q=flysystem) package, so any of the adapters that have been built can be implemented into GitDeploy.

Feel free to PR.

## Contributing

Please see [CONTRIBUTING](https://github.com/hnhdigital-os/git-deploy/blob/master/CONTRIBUTING.md) for details.

## Credits

* [Rocco Howard](https://github.com/therocis)
* [All Contributors](https://github.com/hnhdigital-os/git-deploy/contributors)

## License

The MIT License (MIT). Please see [License File](https://github.com/hnhdigital-os/git-deploy/blob/master/LICENSE) for more information.
