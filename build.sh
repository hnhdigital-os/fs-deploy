#!/bin/bash
#
command -v box >/dev/null 2>&1 || {
    echo "Installing box so we can build our phar executables"
    curl -LSs https://box-project.github.io/box2/installer.php | php
    sudo mv box.phar /usr/local/bin/box;
}

# Update php ini file to allow phars to be built.
if [ "$(php -r "echo ini_get('phar.readonly');")" == "1" ]; then
    INI_LOCATION=$(php --ini | grep 'Loaded Configuration File:         ')
    INI_LOCATION=${INI_LOCATION/Loaded Configuration File:         /}
    echo "Updating phar.readonly ini setting in ${INI_LOCATION}"
    sudo sed -i.bak "${INI_LOCATION}" -e 's/;phar.readonly = On/phar.readonly = Off/g' 
fi

# config
root=`pwd`
build="fs-deploy-src"
buildphar="fs-deploy.phar"
repo="git@github.com:hnhdigital-os/fs-deploy.git"
composer="composer"
target="web"
mode="tags"

if [ "" != "$1" ]
then
    mode="$1"
fi


# init
if [ ! -d "$root/$build" ]
then
    cd $root
    git clone $repo $build
fi

cd "$root/$build"

# update master
git fetch -q origin && \
git fetch --tags -q origin && \
git checkout master -q --force && \
git rebase origin/master -q

version=`git log --pretty="%H" -n1 HEAD`

# create latest dev build
if [ "dev" == "$mode" ]
then
    if [ ! -f "$root/$target/$version" -o "$version" != "`cat \"$root/$target/snapshot\"`" ]
    then
        mkdir -p "$root/$target/download/snapshot/"
        $composer install -q --no-dev && \
        bin/compile $version && \
        touch --date="`git log -n1 --pretty=%ci HEAD`" "$buildphar" && \
        git reset --hard -q $version && \
        echo $version > "$root/$target/snapshot_new" && \
        mv "$buildphar" "$root/$target/download/snapshot/$buildphar" && \
        echo $version > "$root/$target/snapshot_new" && \
        mv "$root/$target/snapshot_new" "$root/$target/snapshot"
    fi
fi

# create tagged releases
for version in `git tag`; do
    if [ ! -f "$root/$target/download/$version/$buildphar" ]; then
        mkdir -p "$root/$target/download/$version/"
        git checkout $version -q && \
        $composer install -q --no-dev && \
        bin/compile $version && \
        touch --date="`git log -n1 --pretty=%ci $version`" "$buildphar" && \
        git reset --hard -q $version && \
        mv "$buildphar" "$root/$target/download/$version/$buildphar"
        echo "$target/download/$version/$buildphar (and .sig) was just built and should be committed to the repo"
    else
        touch --date="`git log -n1 --pretty=%ci $version`" "$root/$target/download/$version/$buildphar"
    fi
done

lastStableVersion=$(ls "$root/$target/download" --ignore snapshot | grep -E '^[0-9.]+$' | xargs -I@ git log --format=format:"%ai @%n" -1 @ | sort -r | head -1 | awk '{print $4}')
lastVersion=$(ls "$root/$target/download" --ignore snapshot | xargs -I@ git log --format=format:"%ai @%n" -1 @ | sort -r | head -1 | awk '{print $4}')
lastSnapshot=$(head -c40 "$root/$target/snapshot")
read -r -d '' versions << EOM
{
    "stable": [{"path": "/download/$lastStableVersion/installer.phar", "version": "$lastStableVersion", "min-php": 70000}],
    "preview": [{"path": "/download/$lastVersion/installer.phar", "version": "$lastVersion", "min-php": 70000}],
    "snapshot": [{"path": "/download/snapshot/installer.phar", "version": "$lastSnapshot", "min-php": 70000}]
}
EOM
echo "$lastStableVersion" > "$root/$target/stable"
echo "$lastVersion" > "$root/$target/preview"
echo "$versions" > "$root/$target/versions_new" && mv "$root/$target/versions_new" "$root/$target/versions"