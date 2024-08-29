package org.vufind.index;
/**
 * Copyright (C) imCode Partner AB 2023
 *
 */

import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.DataField;

import java.io.*;
import java.util.Iterator;
import java.util.Set;
import javax.xml.parsers.DocumentBuilder;
import javax.xml.parsers.DocumentBuilderFactory;
import org.w3c.dom.Document;
import org.w3c.dom.Node;
import org.w3c.dom.NodeList;
import org.apache.log4j.Logger;
import org.solrmarc.index.SolrIndexer;
import org.solrmarc.tools.SolrMarcIndexerException;

/**
 * Full text retrieval indexing routines.
 */
public class FormatString
{
    // Initialize logging category
    static Logger logger = Logger.getLogger(FormatString.class.getName());

     public String setFormatStringReplace(org.marc4j.marc.Record record, String Field,String Pattern,String Value)
     {
        String id = SolrIndexer.instance().getFirstFieldVal(record, Field);
        if (id != null){
          return id.replaceAll(Pattern,Value);
        } else return "";
        //    return "i-"+Pattern;

      }
    /**
     * Log an error message and throw a fatal exception.
     * @param msg message to log
     */
    private void dieWithError(String msg)
    {
        logger.error(msg);
        throw new SolrMarcIndexerException(SolrMarcIndexerException.EXIT, msg);
    }
}
