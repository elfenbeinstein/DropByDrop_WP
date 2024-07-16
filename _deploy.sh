# In Plesk, deploy with bash _deploy.sh >> deployment.log 2>&1

echo ----------

echo Copying PHP file 

### Copy website
cp -r /JsonFiles/ ~/data/JsonFiles/
cp -r /GameBuild/ ~/data/GameBuild/
#cp index.php ~/data/index.php

#cp -RT index.php ~/data/index.php

echo ----------
