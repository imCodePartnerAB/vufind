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
sys.path.append('/srv/script')
import python_config

# Main is called last in file, as python needs function above.
# Having them in order seem easier for me.
def main():
    os.system("rsync -avhr --exclude=cache --exclude=harvest /usr/local/vufind/local/ /srv/github/vufind/local")
    delete_old_settings_in_sql()
    save_config()
    #print(get_server_id())
    #

def save_config():
    mydb = connect(**python_config.mysql)
    mysql = mydb.cursor(buffered=True)
    query = ("SELECT `file`,section, `key` FROM `imAppmgr`.`vufind_settings_to_save` ORDER BY `file`")
    mysql.execute(query)
    updater = ConfigUpdater(strict=False)
    for (inifile,section, key) in mysql:
        if updater._filename is None:
            updater.read(inifile)
        elif inifile != updater._filename: 
            updater.update_file()
            updater.read(inifile)
        
        if updater.has_option(section, key):
            insert_settings_to_sql(inifile,section,key,updater[section][key].value)
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


main()
