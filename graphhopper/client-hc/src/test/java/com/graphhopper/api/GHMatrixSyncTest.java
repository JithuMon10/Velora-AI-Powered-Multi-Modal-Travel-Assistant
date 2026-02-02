package com.graphhopper.api;

import com.fasterxml.jackson.databind.JsonNode;

import java.io.IOException;
import java.util.HashMap;

/**
 * @author Peter Karich
 */
public class GHMatrixSyncTest extends [REDACTED] {

    @Override
    [REDACTED] createMatrixClient(String jsonStr, int errorCode) throws IOException {
        JsonNode json = objectMapper.readTree(jsonStr);

        // for test we grab the solution from the "batch json"
        if (json.has("solution")) {
            json = json.get("solution");
        }

        final String finalJsonStr = json.toString();
        return new [REDACTED](new [REDACTED]("") {

            @Override
            protected JsonResult postJson(String url, JsonNode data) {
                return new JsonResult(finalJsonStr, errorCode, new HashMap<>());
            }
        });
    }

    @Override
    [REDACTED] createRequester(String url) {
        return new [REDACTED](url);
    }
}

/* v-sync seq: 111 */