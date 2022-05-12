#!/usr/bin/python
#
import os
import socket
import re # For regexp functions
from configupdater import ConfigUpdater # https://github.com/pyscaffold/configupdater
from collections import OrderedDict #The class to manage inifile read logic
from  os.path import exists #Used to check the existance of files.
from mysql.connector import connect #My/Maria SQL connection handling
from sqlescapy import sqlescape #Helpts to escape strings for SQL queries 
import sys
from git import Repo
import shutil
import datetime
sys.path.append('/srv/script')
import python_config
    
vufinddir = '/usr/local/vufind'
gittmpdir = '/tmp/vufind-lots'

# Main is called last in file, as python needs function above.
# Having them in order seem easier for me.
def main():
    git_pull_to_tmp()
    delete_old_settings_in_sql()
    #print(get_server_id())
    #

def git_pull_to_tmp():
    username = python_config.git['username']
    password = python_config.git['password']
    if os.path.isdir(gittmpdir):
        shutil.rmtree(gittmpdir)
    tag = get_git_tag()
    #print(tag)
    options = ['-b {}'.format(tag),'--depth 1']
    repo = Repo.clone_from(f'https://{username}:{password}@github.com/imCodePartnerAB/vufind.git', gittmpdir, None, None, options)
    return
    
    os.system("rsync -avhr --exclude=cache --exclude=harvest "+vufinddir+"/local/ "+gittmpdir+"/local")
    save_config()
    
    os.system('rm /tmp/vufind-lots/local/config/gurka')
    
    gitchanged=False
    repo.git.add(all=True)
    for diff in repo.head.commit.diff():
        gitchanged=True
        print(diff.a_path)
        print(diff.b_path)
        print(diff.new_file)
        print(diff.change_type)
    if gitchanged:
        repo.index.commit('Config script save {}'.format(datetime.datetime.now()))
        origin = repo.remote(name='origin')
        origin.push()


    

def save_config():
    mydb = connect(**python_config.mysql)
    mysql = mydb.cursor(buffered=True)
    query = ("SELECT `file`,section, `key` FROM `imAppmgr`.`vufind_settings_to_save` ORDER BY `file`")
    mysql.execute(query)
    updater = ConfigUpdater(strict=False)
    for (inipath,section, key) in mysql:
        inifile = gittmpdir+"/"+inipath
        if updater._filename is None:
            updater.read(inifile)
        elif inifile != updater._filename: 
            updater.update_file()
            updater.read(inifile)
        
        if updater.has_option(section, key):
            insert_settings_to_sql(inipath,section,key,updater[section][key].value)
            print("{}: [{}][{}]={}".format(inifile,section,key,updater[section][key].value))
            updater[section][key] = "Nope"
        print()
    mysql.close()
    mydb.close()



"""
    Update the setting into icm database table:vufind_settings.
"""
def insert_settings_to_sql(inifile, section, key, value):
    server_id = get_server_id()
    mydb = connect(**python_config.mysql)
    #mysql = mydb.cursor()
    mysql = mydb.cursor(buffered=True)
    mysql.execute("INSERT INTO `imAppmgr`.`vufind_settings` (`server_id`, `file`, `section`, `key`, `value`, `to_delete`) VALUES ('{}', '{}', '{}', '{}', '{}', 'Y');".format(server_id,inifile,section,key.replace('[]','[0]'),sqlescape(', '.join(value.splitlines()))))
    mydb.commit()
    mydb.close()

"""
    Update the setting into icm database table:vufind_settings.
"""
def delete_old_settings_in_sql():
    server_id = get_server_id()
    mydb = connect(**python_config.mysql)
    #mysql = mydb.cursor()
    mysql = mydb.cursor(buffered=True)
    mysql.execute("DELETE FROM `imAppmgr`.`vufind_settings` WHERE  `server_id`={} AND date < DATE_SUB(NOW(), INTERVAL 30 SECOND)".format(server_id))
    mydb.commit()
    mydb.close()

"""
    Gets the server ID where it is run, to connect it in imAppmgr database
"""
def get_server_id():
    mydb = connect(**python_config.mysql)
    mysql = mydb.cursor(buffered=True)
    query = ("SELECT id FROM Servers WHERE name IN (%s)")
    mysql.execute(query, (socket.gethostname(),))
    for (server_id,) in mysql:
        return server_id
    mysql.close()
    mydb.close()

def get_git_tag():
    server_id = get_server_id()
    mydb = connect(**python_config.mysql)
    mysql = mydb.cursor(buffered=True)
    query = ("SELECT git_fork_or_tag FROM `imAppmgr`.`vufind_server_settings` WHERE server_id IN ({})".format(server_id))
    mysql.execute(query)
    for (tag,) in mysql:
        return tag
    mysql.close()
    mydb.close()

main()
