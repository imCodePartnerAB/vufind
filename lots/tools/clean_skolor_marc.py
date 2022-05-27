#!/bin/python
#
#
from pymarc import MARCReader

#from mysql.connector import connect #My/Maria SQL connection handling
#from sqlescapy import sqlescape #Helpts to escape strings for SQL queries 
#import socket
#import pysolr
#import sys
#import json
#import requests
#import time
#sys.path.append('/srv/script')
#import python_config


#solr_url = 'http://127.0.0.1:8983/solr/biblio'

with open('/tmp/biblios.mrc', 'rb') as fh:
    reader = MARCReader(fh)
    for record in reader:
        for f in record.get_fields('001'):
            print("001: ",f)
        print(record.get_fields('001'))

