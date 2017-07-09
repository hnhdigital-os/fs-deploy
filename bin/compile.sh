#!/bin/bash

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

version="$1"

if [ "" == "$version" ]
then
    version=`git log --pretty="%H" -n1 HEAD`
fi

release=$(php -r "echo (strlen('$version') == 40) ? 'snapshot' : 'stable';")

echo "$release-$version"

sed -i s/RELEASE-VERSION/$release-$version/g $DIR/main

box build

sed -i s/$release-$version/RELEASE-VERSION/g $DIR/main