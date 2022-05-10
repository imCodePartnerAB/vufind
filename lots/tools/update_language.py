#!/usr/bin/python
import configparser #To work with config files
import os
from os.path import exists #Used to check the existance of files.

"""

    Find all local language files and update them with missing keys from
    the original files. This is needed on new versions where there has
    come new values.

"""

"""
    The main function, find the files and if the original exist then
    send to compare.
"""
def main():
    for inifile in os.popen('find /usr/local/vufind/local/languages -type f -name *.ini|grep language').read().split('\n')[0:-1]:
        if not exists(inifile.replace('vufind/local/','vufind/')):
            print('Can\'t find file to compare with {}'.format(inifile))
        else:
            compare_inifiles(inifile,inifile.replace('vufind/local/','vufind/'))

"""
    Compare the files, open both files and see which keys are missing
    from the local file. Transfer the key = value from the version defaults.
"""
def compare_inifiles(inifile1, inifile2):
    config1 = configparser.RawConfigParser(strict=False)
    config2 = configparser.RawConfigParser(strict=False)
    config1.optionxform=str
    config2.optionxform=str

    #Language files need to have [top] added for the configparser
    #else just do a read.
    with open(inifile1) as stream:
        config1.read_string("[top]\n" + stream.read()) 
    with open(inifile2) as stream:
        config2.read_string("[top]\n" + stream.read()) 

    # Iterate sections and all itemkeys in them
    for section_name in config2.sections():
      for key, value in config2.items(section_name):
          if not config1.has_option(section_name,key):
            print('Updating {}:{}:{} = {}'.format(inifile1,section_name,key,value))
            config1.set(section_name,key,value)
    
    # We need to write the values one by one to not get the [top] att
    # start of the file, wich is not part of language files as they 
    # are propertie files not ini files.
    with open(inifile1, 'w') as f: 
        for key, value in sorted(config1.items('top')):
            f.write('%s = %s\n' % (key, value))

main()
