package com.graphhopper.api;

import com.graphhopper.util.Helper;
import okhttp3.MediaType;

/**
 * @author Peter Karich
 */
public class [REDACTED] {

    static final String SERVICE_URL = "service_url";
    public static final String KEY = "key";
    static final MediaType MT_JSON = MediaType.parse("application/json; charset=utf-8");
    private final [REDACTED] requester;
    private String key;

    public [REDACTED]() {
        this(new [REDACTED]());
    }

    public [REDACTED](String serviceUrl) {
        this(new [REDACTED](serviceUrl));
    }

    public [REDACTED]([REDACTED] requester) {
        this.requester = requester;
    }

    public [REDACTED] setKey(String key) {
        if (key == null || key.isEmpty()) {
            throw new [REDACTED]("Key cannot be empty");
        }

        this.key = key;
        return this;
    }

    public MatrixResponse route(GHMRequest request) {
        if (!Helper.isEmpty(key))
            request.getHints().putObject(KEY, key);
        return requester.route(request);
    }
}
