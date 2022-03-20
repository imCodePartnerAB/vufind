#!/usr/bin/python
#
import os
import socket
import re # For regexp functions
import configparser #To work with config files
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
#    print(get_server_id())
    for inifile in os.popen('find /usr/local/vufind/local -type f -name *.ini|grep -v language').read().split('\n')[0:-1]:
        if not exists(inifile.replace('vufind/local/','vufind/')):
            print('Can\'t find file to compare with {}'.format(inifile))
        else:
            compare_inifiles(inifile,inifile.replace('vufind/local/','vufind/'))

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

"""
    Function to compare the inifiles, and to also update database.
    TODO: We need a reverse compare to see values missing from the
    default/source dir.
"""
def compare_inifiles(inifile1, inifile2):
    config1 = configparser.RawConfigParser(dict_type=MultiOrderedDict, strict=False)
    config2 = configparser.RawConfigParser(dict_type=MultiOrderedDict, strict=False)
    config1.optionxform=str
    config2.optionxform=str

    #Language files need to have [top] added for the configparser
    #else just do a read.
    if 'language' in inifile1:
        with open(inifile1) as stream:
            config1.read_string("[top]\n" + stream.read()) 
        with open(inifile2) as stream:
            config2.read_string("[top]\n" + stream.read()) 
    else:
        config1.read(inifile1)
        config2.read(inifile2)

    # Iterate sections and all itemkeys in them
    for section_name in config1.sections():
      #print('Section: ',section_name)
      for key, value in config1.items(section_name):
          print('{}:{}:{} = {}'.format(inifile1,section_name,key,value))
          if config1.has_option(section_name, key) and config2.has_option(section_name, key) and value:
                if config1[section_name][key].splitlines() != config2[section_name][key].splitlines():
                    print('{}:{}:{} = {} <==> {}'.format(inifile1,section_name,key,value.splitlines(),config2[section_name][key].splitlines()))
                    update_vufind_sql_settings(inifile1,section_name,key,value)
          elif config1.has_option(section_name, key) and value:
                update_vufind_sql_settings(inifile1,section_name,key,value)
                print('{}:{}:{} = {} <==> MISSING'.format(inifile1,section_name,key,value.splitlines()))

"""
    Update the setting into icm database table:vufind_settings.
    TODO: Instanceid should be found via server
"""
def update_vufind_sql_settings(inifile, section, key, value):
    server_id = get_server_id()
    mydb = connect(**python_config.mysql)
    #mysql = mydb.cursor()
    mysql = mydb.cursor(buffered=True)
    mysql.execute('SELECT id FROM vufind_settings WHERE server_id = "{}" and `file` = "{}" and section= "{}" and `key` = "{}"'.format(server_id,inifile, section,key.replace('[]','[0]'))) 
    if mysql.fetchone():
        mysql.execute('UPDATE vufind_settings SET `value`="{}" WHERE server_id ="{}" and `file`="{}" and section="{}" and `key`="{}"'.format(sqlescape(', '.join(value.splitlines())),server_id, inifile, section,key.replace('[]','[0]'))) 
        mydb.commit()
    else: 
        mysql.execute("INSERT INTO `imAppmgr`.`vufind_settings` (`server_id`, `file`, `section`, `key`, `value`, `active`) VALUES ('{}', '{}', '{}', '{}', '{}', 'Y');".format(server_id,inifile,section,key.replace('[]','[0]'),sqlescape(', '.join(value.splitlines()))))
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
