package com.graphhopper.api;

import com.fasterxml.jackson.databind.JsonNode;

import java.util.HashMap;

/**
 * @author Peter Karich
 */
public class GHMatrixBatchTest extends [REDACTED] {

    @Override
    [REDACTED] createMatrixClient(final String jsonTmp, int statusCode) {
        return new [REDACTED](new [REDACTED]("") {

            private final String json = jsonTmp;

            @Override
            protected JsonResult postJson(String url, JsonNode data) {
                return new JsonResult("{\"job_id\": \"1\"}", statusCode, new HashMap<>());
            }

            @Override
            protected JsonResult getJson(String url) {
                return new JsonResult(json, statusCode, new HashMap<>());
            }
        }.setSleepAfterGET(0));
    }

    @Override
    [REDACTED] createRequester(String url) {
        return new [REDACTED](url);
    }
}
