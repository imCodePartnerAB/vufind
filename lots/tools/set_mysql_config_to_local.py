#!/usr/bin/python
#
import os
import sys
import shutil
import socket
import re # For regexp functions
from  os.path import exists #Used to check the existance of files.
import iniparse #To work with config files
from collections import OrderedDict #The class to manage inifile read logic
from mysql.connector import connect #My/Maria SQL connection handling
sys.path.append('/srv/script')
import python_config

# Main is called last in file, as python needs function above.
# Having them in order seem easier for me.
def main():
    create_ini_files()
    iterate_values_from_sql()

"""
 Class to help fixing input keys
 ==============================
   when keys end with [] in vufind it means an array.
   ex.
       translated_facets[] = institution
       translated_facets[] = building
       translated_facets[] = format

   With python though and ini really you write lists
   another way:
       translated_facets = institution, building, format

   Neither works for us, as they are to be translated to
   php. So what this function does is to turn it to:
       translated_facets[] = institution
       translated_facets[1] = building
       translated_facets[2] = format
   which should be a valid array as [] should become [0] anyway
"""
class MultiOrderedDict(OrderedDict):
    def __setitem__(self, key, value):
        re_value=value

        if (isinstance(value, list) and key in self and '[]' in key):
            print('isInstance key: {} value: {}'.format(key,re_value))
            i = 1
            while True:
                if key.replace('[]','[{}]'.format(i)) in self:
                        i += 1
                else:
                    self[key.replace('[]','[{}]'.format(i))] = re_value
                    break
        else:
            super(MultiOrderedDict, self).__setitem__(key, re_value)

def create_ini_files():
    server_id = get_server_id()
    mydb = connect(**python_config.mysql)
    mysql = mydb.cursor(buffered=True)
    query = ("SELECT * from vufind_settings WHERE server_id = %s GROUP BY file")
    mysql.execute(query, (server_id,))
    for (id, server_id, inifile, section, key, value, to_delete) in mysql:
        if ('oai.ini' in inifile):
            shutil.copyfile('/usr/local/vufind/harvest/oai.ini','/usr/local/vufind/local/config/vufind/oai.ini')
        else:
            shutil.copyfile(inifile.replace('vufind/local/','vufind/'), inifile)
        print("copying file: ",inifile)

def iterate_values_from_sql():
    server_id = get_server_id()
    mydb = connect(**python_config.mysql)
    mysql = mydb.cursor(buffered=True)
    query = ("SELECT * from vufind_settings WHERE server_id = %s")
    mysql.execute(query, (server_id,))
    for (id, server_id, inifile, section, key, value, to_delete) in mysql:
        print("Setting: %s [%s] %s=%s" % (inifile, section, key, value))
        setValue(inifile,section, key,value)


def setValue(inifile, section, key, value):
    config = iniparse.ConfigParser()
    config.optionxform=str
    config.read(inifile)
    if config == []:
        print("Could not load %s." % inifile)
        if not os.path.exists(inifile):
            print("config.ini does not exist.")
    if ('[0]' in key):
        tmp_key = key.replace('[0]','[]')
        while True:
            if config.remove_option(section,tmp_key):
                break;

    if config.has_section(section):
        config.set(section,key,str(value))
    else:
        config.add_section(section)
        config.set(section,key,str(value))

    #config[section][key] = value
    inifile = open(inifile, 'w')
    config.write(inifile)
    #config.write(inifile)
#    with open(inifile, 'wb') as configfile:
#        config.write(configfile)

def get_server_id():
    if (len(sys.argv) > 1):
        return sys.argv[1]
    mydb = connect(**python_config.mysql)
    mysql = mydb.cursor(buffered=True)
    query = ("SELECT id FROM Servers WHERE name IN (%s)")
    mysql.execute(query, (socket.gethostname(),))
    for (server_id,) in mysql:
        return server_id

    
    


main()
