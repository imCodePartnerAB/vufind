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
    #

def save_config(sections)
    inifile = "/srv/github/vufind/local/config/vufind/config.ini"
    updater = ConfigUpdater(strict=False)
    updater.read(inifile)
    updater.update_file()

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


main()
