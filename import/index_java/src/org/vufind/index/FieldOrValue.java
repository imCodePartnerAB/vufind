package org.vufind.index;
/**
 * Full text retrieval indexing routines.
 *
 * Copyright (C) Villanova University 2017.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
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
public class FieldOrValue
{
    // Initialize logging category
    static Logger logger = Logger.getLogger(FieldOrValue.class.getName());

    public String setFieldOrValue(org.marc4j.marc.Record record, String field, String thisvalue)
    {
        String fieldValue = SolrIndexer.instance().getFirstFieldVal(record, field);
        if (fieldValue != null) {
            return fieldValue;
        } else {
            return thisvalue;
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

