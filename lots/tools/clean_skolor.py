#!/bin/python
#
#

from mysql.connector import connect #My/Maria SQL connection handling
from sqlescapy import sqlescape #Helpts to escape strings for SQL queries 
import socket
import pysolr
import sys
import json
import requests
import time
sys.path.append('/srv/script')
import python_config


solr_url = 'http://127.0.0.1:8983/solr/biblio'

# Main is called last in file, as python needs function above.
# Having them in order seem easier for me.
def main():
    iterate_biblio_items_to_delete()
    print('iterate_biblio_items_to_delete')
    #delete_institution_building_values('BJORKNAS')
    #delete_institution_building_values('FRIDHEM')
    #delete_institution_building_values('FURUHED')
    #delete_institution_building_values('LULEAGYMNA')
    #delete_institution_building_values('LULEAVUXEN')
    #delete_institution_building_values('LULEAKULT')
    #delete_institution_building_values('MUSIKDANS')
    #delete_institution_building_values('NYBORG')
    #delete_institution_building_values('RINGEL')
    #delete_institution_building_values('SANDBACKA')
    #delete_institution_building_values('TUNASKOLAN')
    #delete_institution_building_values('KNUTLUNDMA')
    #delete_institution_building_values('PARKSKOLAN')
    #delete_institution_building_values('VISTTRASK')
    delete_biblio_all_empty()

def iterate_biblio_items_to_delete():
    mydb = connect(**python_config.bin_mysql)
    mysql = mydb.cursor(buffered=True)
    query = ("""
    SELECT b.biblionumber, i.homebranch FROM biblio b, items i
	WHERE i.biblionumber = b.biblionumber AND
            b.biblionumber NOT IN (
        	SELECT bi.biblionumber FROM biblioitems bi, items i
				WHERE i.biblioitemnumber = bi.biblioitemnumber AND
					i.homebranch NOT IN ('BJORKNAS', 'CENTRALELE', 'FRIDHEM', 'FURUHED', 'HJALMARLUN', 'KAPPRUM', 'KNUTLUNDMA', 'LULEAGYMNA', 
                                        'LULEAKULT', 'LULEAVUXEN', 'MANHEM', 'MUSIKDANS', 'NYBORG', 'PARKSKOLAN', 'RINGEL', 'SANDBACKA', 'TUNASKOLAN', 'VISTTRASK')
	)
        ORDER BY i.homebranch

            """)
    mysql.execute(query,)
    homebranch_tmp = ""
    for (biblionumber, homebranch, ) in mysql:
        if homebranch != homebranch_tmp:
            if homebranch_tmp != "":
                print("Deleting branch: ",homebranch_tmp)
                delete_institution_building_values(homebranch_tmp)
            homebranch_tmp = homebranch
            
        print(biblionumber, " " , homebranch)
        delete_biblio_item(biblionumber)
        #delete_institution_building_values(homebranch)
        #time.sleep(0.1)
    if homebranch_tmp != "":
        print("Deleting branch: ",homebranch_tmp)
        delete_institution_building_values(homebranch_tmp)

def delete_biblio_item(biblionumber):
    solr = pysolr.Solr(solr_url, timeout=100)
    
    results = solr.search('id:'+str(biblionumber), rows=0)
    results = solr.search('id:'+str(biblionumber), rows=results.hits+1)
    for result in results:
        print('Deleting: ', result)
        solr.delete(id=result['id'], commit=True)

def delete_institution_building_values(value):
    solr = pysolr.Solr(solr_url, timeout=100)
    print('')
    #results = solr.search('institution:(BJORKNAS) OR institution:(FRIDHEM) OR institution:(FURUHED) OR institution:(LULEAGYMNA) OR institution:(LULEAVUXEN) OR institution:(LULEAKULT) OR institution:(MUSIKDANS) OR institution:(NYBORG) OR institution:(RINGEL) OR institution:(SANDBACKA) OR institution:(TUNASKOLAN) OR institution:(KNUTLUNDMA) OR institution:(PARKSKOLAN) OR institution:(VISTTRASK)', rows=10)
    results = solr.search('institution:('+value+') OR building:('+value+')', rows=0)
    results = solr.search('institution:('+value+') OR building:('+value+')', rows=results.hits+1)
    print('hits: ',results.hits)
    print()
    #results = solr.search('institution:(BJORKNAS)')
    #print("Saw {0} result(s).".format(len(results)))
    for result in results:
        print(result['id'])
        print('============')
        #print(result)
        print()
        payload = [{
            "id":result['id'],
            "institution":{"remove":value},
            "building":{"remove":value}
            }]
        
        print('Deleting: ', result['id'])
        response=requests.post(
                solr_url+'/update?commit=true',
                data=json.dumps(payload),
                headers={'content-type': "application/json"}
                )
        print(response.content)
        print()
        print('============')

    #solr.delete(id=value, commit=True)


def delete_biblio_all_empty():
    solr = pysolr.Solr(solr_url, timeout=10)
    results = solr.search('NOT institution:* AND NOT building:*', rows=0)
    results = solr.search('NOT institution:* AND NOT building:*', rows=results.hits+1)
    #print("Saw {0} result(s).".format(len(results)))
    for result in results:
        print('Deleting: ', result['id'])
        solr.delete(id=result['id'], commit=True)

main()
