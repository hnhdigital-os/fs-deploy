```
___________    ________                .__                
\_   _____/____\______ \   ____ ______ |  |   ____ ___.__.
 |    __)/  ___/|    |  \_/ __ \\____ \|  |  /  _ <   |  |
 |     \ \___ \ |    `   \  ___/|  |_> >  |_(  <_> )___  |
 \___  //____  >_______  /\___  >   __/|____/\____// ____|
     \/      \/        \/     \/|__|               \/     

```
Provides the ability to create deployment configuration that can be used to deploy files to a remote (or local) filesystem.

[![Github Releases](https://img.shields.io/github/downloads/hnhdigital-os/fs-deploy/latest/total.svg)](https://github.com/hnhdigital-os/fs-deploy) [![GitHub release](https://img.shields.io/github/release/hnhdigital-os/fs-deploy.svg)]()

[![StyleCI](https://styleci.io/repos/96600391/shield?branch=master)](https://styleci.io/repos/96600391) [![Issue Count](https://codeclimate.com/github/hnhdigital-os/fs-deploy/badges/issue_count.svg)](https://codeclimate.com/github/hnhdigital-os/fs-deploy) [![Code Climate](https://codeclimate.com/github/hnhdigital-os/fs-deploy/badges/gpa.svg)](https://codeclimate.com/github/hnhdigital-os/fs-deploy) 

This package has been developed by H&H|Digital, an Australian botique developer. Visit us at [hnh.digital](http://hnh.digital).


## Install

`$ bash <(curl -s https://hnhdigital-os.github.io/fs-deploy/install)`

`$ mv fs-deploy /usr/local/bin/fs-deploy`

## Usage

GitDepoy created a .fsdeploy configuration file in the root directory of your project. It will automatically add this to your .gitignore file.

Simply run fs-deploy in your project root folder:

`$ fs-deploy config`

Follow the prompts and then you can:

`$ fs-deploy deploy`

FsDeploy comes with the ability to self-update:

`$ fs-deploy self-update`

## Available remote filesystems

* AWS S3

## Missing filesystems

FsDeploy uses the [Flysystem](https://github.com/thephpleague?utf8=✓&q=flysystem) package, so any of the adapters that have been built can be implemented into FsDeploy.

Feel free to PR.

## Contributing

Please see [CONTRIBUTING](https://github.com/hnhdigital-os/fs-deploy/blob/master/CONTRIBUTING.md) for details.

## Credits

* [Rocco Howard](https://github.com/therocis)
* [All Contributors](https://github.com/hnhdigital-os/fs-deploy/contributors)

## License

The MIT License (MIT). Please see [License File](https://github.com/hnhdigital-os/fs-deploy/blob/master/LICENSE) for more information.
