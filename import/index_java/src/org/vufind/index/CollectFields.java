package org.vufind.index;
/**
 * Copyright (C) imCode Partner AB 2023
 *
 */

import org.marc4j.marc.Record;
import org.marc4j.marc.Subfield;
import org.marc4j.marc.DataField;

import java.io.*;
import java.util.List;
import java.util.Set;
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
public class CollectFields
{
    // Initialize logging category
    static Logger logger = Logger.getLogger(FormatString.class.getName());

    // Example: arb = custom, fieldOnlyContainingOnlyText(952,952c, "ARB", "true", "true")
    //
public String fieldOnlyContainingOnlyText(org.marc4j.marc.Record record, String rootField, String field, String text, String countEmpty, String doLog) {

    logger.info("countEmpty: " + countEmpty + " log: " + doLog);
    String id = SolrIndexer.instance().getFirstFieldVal(record, "999c");
    Set<String> rootFieldList = SolrIndexer.instance().getFieldList(record, rootField);
    List<String> fieldList = SolrIndexer.instance().getFieldListAsList(record, field);

    // Convert countEmpty and doLog to lowercase and compare with "1"
    if ("2".equals(doLog)) {
        logger.info(fieldList);
        logger.info(rootFieldList);
    }

    if (rootFieldList.size() == 0) {
        if ("1".equals(doLog) || "2".equals(doLog)) {
            logger.info("FOCO id: " + id + " Size fieldList: " + fieldList.size() + " rootFieldList: " + rootFieldList.size() + " [none]");
        }
      return "none";
    }
    else if (fieldList.size() != rootFieldList.size() && "1".equals(countEmpty)) {
        if ("1".equals(doLog) || "2".equals(doLog)) {
            logger.info("FOCO id: " + id + " Size fieldList: " + fieldList.size() + " rootFieldList: " + rootFieldList.size() + " [overload]");
        }
        return "overload";
    } else {
        String ret = fieldList.stream().allMatch(text::equals) ? "only" : "more";
        if ("1".equals(doLog)) {
            logger.info("FOCO id: " + id + " Size fieldList: " + fieldList.size() + " rootFieldList: " + rootFieldList.size() + " ["+ret+"]");
        }
        return ret;
    }
}

    public String ffieldOnlyContainingOnlyText(org.marc4j.marc.Record record, String rootField, String field, String text, String countEmpty, String doLog)
    {
       
       logger.info("countEmpty:"+countEmpty+" log:"+doLog);
       String id = SolrIndexer.instance().getFirstFieldVal(record, "999c");
       Set<String> rootFieldList = SolrIndexer.instance().getFieldList(record, rootField);
       List<String> fieldList = SolrIndexer.instance().getFieldListAsList(record, field);
       if (doLog == "1") logger.info(fieldList);
       if (doLog == "1") logger.info("id: "+id+" Size fieldList:"+fieldList.size()+" rootFieldList:"+rootFieldList.size());
       if (doLog == "1") logger.info(rootFieldList);
       if (fieldList.size() != rootFieldList.size() && countEmpty != "1") {
         if (doLog == "1") logger.info("Field size inconsistent!");
         return "overload";
        
       } else {
        return fieldList.stream().allMatch(text::equals) ? "only" : "more";

       }
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
