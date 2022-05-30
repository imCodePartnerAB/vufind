#!/bin/python
#
#
from mysql.connector import connect  # My/Maria SQL connection handling
from sqlescapy import sqlescape  # Helpts to escape strings for SQL queries
import socket
import pysolr
import sys
import json
import requests
import time

sys.path.append("/srv/script")
import python_config


solr_url = "http://127.0.0.1:8983/solr/biblio"

# Main is called last in file, as python needs function above.
# Having them in order seem easier for me.
def main():
    print("iterate_biblio_items_to_delete")
    schools = (
        "BJORKNAS",
        "CENTRALELE",
        "FRIDHEM",
        "FURUHED",
        "HJALMARLUN",
        "KAPPRUM",
        "KNUTLUNDMA",
        "LULEAGYMNA",
        "LULEAKULT",
        "LULEAVUXEN",
        "MANHEM",
        "MUSIKDANS",
        "NYBORG",
        "PARKSKOLAN",
        "RINGEL",
        "SANDBACKA",
        "TUNASKOLAN",
        "VISTTRASK",
    )
    # iterate_biblio_items_to_delete(schools)

    for school in schools:
        print("delete_institution_building_values(%s)" % school)
        delete_institution_building_values(school)

    print("delete_biblio_all_empty()")
    delete_biblio_all_empty()


def iterate_biblio_items_to_delete(schools):
    mydb = connect(**python_config.bin_mysql)
    mysql = mydb.cursor(buffered=True)
    query = """
    SELECT b.biblionumber, i.homebranch FROM biblio b, items i
  WHERE i.biblionumber = b.biblionumber AND
            b.biblionumber NOT IN (
          SELECT bi.biblionumber FROM biblioitems bi, items i
        WHERE i.biblioitemnumber = bi.biblioitemnumber AND
          i.homebranch NOT IN {}
  )
        ORDER BY i.homebranch

            """.format(
        schools
    )

    mysql.execute(
        query,
    )
    homebranch_tmp = ""
    biblionumbers = ""
    nr_boolean_clause = 0
    for (
        biblionumber,
        homebranch,
    ) in mysql:
        if biblionumbers == "":
            biblionumbers = biblionumber
        else:
            biblionumbers = "{} OR {}".format(biblionumbers, biblionumber)
        nr_boolean_clause += 1
        if nr_boolean_clause >= 1000:
            delete_biblio_item_bulk(biblionumbers)
            nr_boolean_clause = 0
            biblionumbers = ""
    delete_biblio_item_bulk(biblionumbers)


def delete_biblio_item_bulk(biblionumbers):
    solr = pysolr.Solr(solr_url, timeout=100)
    print("id:(%s)" % biblionumbers)
    results = solr.search("id:(%s)" % biblionumbers, rows=0)
    print("Hits: %s" % str(results.hits))
    if results.hits == 0:
        return
    # results = solr.search("id:({})".format(str(biblionumbers)), rows=results.hits+1)
    solr.delete(q="id:(%s)" % biblionumbers, commit=False)
    payload = [
        {
            "id": "(%s)" % biblionumbers,
        }
    ]

    response = requests.post(
        solr_url + "/update?commit=true",
        data=json.dumps(payload),
        headers={"content-type": "application/json"},
    )
    print(response.content)


def delete_institution_building_values(value):
    solr = pysolr.Solr(solr_url, timeout=100)
    print("")
    # results = solr.search('institution:(BJORKNAS) OR institution:(FRIDHEM) OR institution:(FURUHED) OR institution:(LULEAGYMNA) OR institution:(LULEAVUXEN) OR institution:(LULEAKULT) OR institution:(MUSIKDANS) OR institution:(NYBORG) OR institution:(RINGEL) OR institution:(SANDBACKA) OR institution:(TUNASKOLAN) OR institution:(KNUTLUNDMA) OR institution:(PARKSKOLAN) OR institution:(VISTTRASK)', rows=10)
    results = solr.search(
        "institution:(" + value + ") OR building:(" + value + ")", rows=0
    )
    results = solr.search(
        "institution:(" + value + ") OR building:(" + value + ")", rows=results.hits + 1
    )
    print("hits: ", results.hits)
    print()
    payload = []
    # results = solr.search('institution:(BJORKNAS)')
    # print("Saw {0} result(s).".format(len(results)))
    for result in results:
        print(result["id"])
        payload.append(
            {
                "id": result["id"],
                "institution": {"remove": value},
                "building": {"remove": value},
            }
        )

    # print(json.dumps(payload))
    response = requests.post(
        solr_url + "/update?commit=true",
        data=json.dumps(payload),
        headers={"content-type": "application/json"},
    )

    #    print("============")
    #    # print(result)
    #    print()
    #
    #    # print('Deleting: ', result['id'])
    #    response = requests.post(
    #        solr_url + "/update?commit=true",
    #        data=json.dumps(payload),
    #        headers={"content-type": "application/json"},
    #    )
    print(response.content)
    print()
    print("============")

    # solr.delete(id=value, commit=True)


def delete_biblio_all_empty():
    solr = pysolr.Solr(solr_url, timeout=10)
    results = solr.search("NOT institution:* AND NOT building:*", rows=0)
    results = solr.search("NOT institution:* AND NOT building:*", rows=results.hits + 1)

    biblionumbers = ""
    nr_boolean_clause = 0
    for result in results:
        print(result["id"], " : ", nr_boolean_clause)
        nr_boolean_clause += 1
        
        if biblionumbers == "":
            biblionumbers = result["id"]
        else:
            biblionumbers = "{} OR {}".format(biblionumbers, result["id"])
        
        if nr_boolean_clause >= 1000:
            delete_biblio_item_bulk(biblionumbers)
            nr_boolean_clause = 0
            biblionumbers = ""

    delete_biblio_item_bulk(biblionumbers)
    print(biblionumbers)
    # print("Saw {0} result(s).".format(len(results)))


#    for result in results:
#        print("Deleting: ", result["id"])
#        solr.delete(id=result["id"], commit=True)


main()
