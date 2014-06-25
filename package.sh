#!/bin/sh

build_package() {
  VERSION=$1
  INSTALLDIR=debian/package/outlet-php-orm-$VERSION
  
  if [ -z "$VERSION" ]; then
    echo "Usage: $0 system package <version>"
  else
    echo Installing in $INSTALLDIR
    [ ! -d "$INSTALLDIR/DEBIAN" ] && mkdir -p "$INSTALLDIR/DEBIAN" 
    [ ! -d "$INSTALLDIR/usr/share/php" ] && mkdir -p "$INSTALLDIR/usr/share/php" 

    cp -r classes/outlet $INSTALLDIR/usr/share/php/
    find $INSTALLDIR -name ".svn" -exec rm -rf {} \;
    echo
    # Evaluate shell variables in control file
    CONTROLFILE="echo \"$(cat debian/control)\""
    eval "$CONTROLFILE" |tee $INSTALLDIR/DEBIAN/control

    cd debian/package/
    fakeroot dpkg-deb --build outlet-php-orm-$VERSION
    if [ -e outlet-php-orm-$VERSION.deb ]; then
      mv outlet-php-orm-$VERSION.deb ../..
      echo done
    else
      error "Failed to create file: outlet-php-orm-$VERSION.deb"
    fi
    cd ../..
    if [ -d $INSTALLDIR ]; then
      echo .
      #rm -r $INSTALLDIR
    fi
  fi
}

build_package $1
