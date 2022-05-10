#!/usr/bin/python
# pip install GitPython
from git import Repo
import os
from shutil import rmtree
import sys
import shutil
sys.path.append('/srv/script')
import python_config

username = python_config.git['username']
password = python_config.git['password']

source_dir = r"/srv/github/v8.0.1"
destination_dir = r"/tmp/gurka"
shutil.move(source_dir, destination_dir)

#os.system('rm -fR  /tmp/vufind-lots')
#rmtree('/tmp/vufind-lots')
#options = ['-b lots-8.0','--depth 1']
#repo = Repo.clone_from(f'https://{username}:{password}@github.com/imCodePartnerAB/vufind.git', '/tmp/vufind-lots', None, None, options)
#heads = repo.heads
#master = heads.lots-8.0
#print(repo.commit('lots-8.0'))

#os.system('echo "AA">>/tmp/vufind-lots/test.txt')

#repo.index.add('/tmp/vufind-lots/test.txt')
#
#repo.index.commit('Testar commit')
#
#origin = repo.remote(name='origin')
#origin.push()
