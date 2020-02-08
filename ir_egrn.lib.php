<?php
function vaadinQuery($uri, $data)
{
    global $ch, $portletFullURL;

    print $data . "\n";

    curl_setopt($ch, CURLOPT_URL, $url = $portletFullURL . '?' . $uri);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: text/plain;charset=UTF-8',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data); // GS, group separator??
    do {
        $result = curl_exec($ch);
        $responseCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($responseCode >= 500) {
// @todo limit retry
            print "Request failed, error {$responseCode}. Retry\n";
        }
    } while ($responseCode >= 500);
    $result = str_replace('for(;;);', '', $result);
    $result = json_decode($result, 1);
    if (!is_array($result)) {
        throw new \RuntimeException('Failed to execute Vaadin query');
    }
    if (isset($result[0]['meta']['appError'])) {
        throw new RuntimeException($result[0]['meta']['appError']['caption']);
    }
    return $result;
}

function vaadinSetFieldText($field, $text)
{
    global $appLogin;

    $textLength = strlen($text);
    vaadinQuery('windowName=1',
//"{$appLogin['Vaadin-Security-Key']}\x1D{$text}\x1F{$field}\x1Ftext\x1Fs\x1E{$textLength}\x1F{$field}\x1Fc\x1Fi"
//"{$appLogin['Vaadin-Security-Key']}\x1D977\x1FPID0\x1Fheight\x1Fi\x1E755\x1FPID0\x1Fwidth\x1Fi\x1E1905\x1FPID0\x1FbrowserWidth\x1Fi\x1E862\x1FPID0\x1FbrowserHeight\x1Fi\x1E{$text}\x1F{$field}\x1Ftext\x1Fs\x1E{$textLength}\x1F{$field}\x1Fc\x1Fi"
        "{$appLogin['Vaadin-Security-Key']}\x1D{$text}\x1F{$field}\x1Ftext\x1Fs\x1E{$textLength}\x1F{$field}\x1Fc\x1Fi"
    );
}

function vaadinClickButton($field)
{
    global $appLogin;
    return vaadinQuery('windowName=1',
        "{$appLogin['Vaadin-Security-Key']}\x1Dtrue\x1F{$field}\x1Fstate\x1Fb\x1E1,0,0,false,false,false,false,1,30,17\x1F{$field}\x1Fmousedetails\x1Fs"
    );

}

function findKeyByCaption(array $data, $caption)
{
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            try {
                return findKeyByCaption($v, $caption);
            } catch (\Throwable $exception) {
                // it's ok, do nothing
            }
        } elseif ($k === 'caption' && $v === $caption) {
            return $data['key'];
        }
    }
    throw new RuntimeException("Button {$caption} not found!");
}

function findButtonByCaption(array $data, $caption)
{
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            try {
                return findButtonByCaption($v, $caption);
            } catch (\Throwable $exception) {
                // it's ok, do nothing
            }
        } elseif ($k === 'caption' && $v === $caption) {
            return $data['id'];
        }
    }
    throw new RuntimeException("Button {$caption} not found!");
}

function findButtonByPrompt(array $data, $caption)
{
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            try {
                return findButtonByPrompt($v, $caption);
            } catch (\Throwable $exception) {
                // it's ok, do nothing
            }
        } elseif ($k === 'prompt' && $v === $caption) {
            return $data['id'];
        }
    }
    throw new RuntimeException("Button {$caption} not found!");
}

function findFieldByStyle(array $data, $style)
{
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            try {
                return findFieldByStyle($v, $style);
            } catch (\Throwable $exception) {
                // it's ok, do nothing
            }
        } elseif ($k === 'style' && $v === $style) {
            return $data['id'];
        }
    }
    throw new RuntimeException("Button {$style} not found!");
}

function findFieldBySelectMode(array $data, $mode)
{
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            try {
                return findFieldBySelectMode($v, $mode);
            } catch (\Throwable $exception) {
                // it's ok, do nothing
            }
        } elseif ($k === 'selectmode' && $v === $mode) {
            return $data['id'];
        }
    }
    throw new RuntimeException("Mode {$mode} not found!");
}

function findSrcByContentType(array $data, $contentType)
{
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            try {
                return findSrcByContentType($v, $contentType);
            } catch (\Throwable $exception) {
                // it's ok, do nothing
            }
        } elseif ($k === 'mimetype' && $v === $contentType) {
            return $data['src'];
        }
    }
    throw new RuntimeException("Src {$contentType} not found!");
}

function findSrcByIcon(array $data, $icon)
{
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            try {
                return findSrcByIcon($v, $icon);
            } catch (\Throwable $exception) {
                // it's ok, do nothing
            }
        } elseif ($k === 'icon' && $v === $icon) {
            return $data['src'];
        }
    }
    throw new RuntimeException("Src {$icon} not found!");
}

function findRegexp(array $data, $regexp)
{
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            try {
                return findRegexp($v, $regexp);
            } catch (\Throwable $exception) {
                // it's ok, do nothing
            }
        } elseif (preg_match($regexp, $v, $matches)) {
            return $matches;
        }
    }
    throw new RuntimeException("Pattern {$regexp} not found!");
}

function findFocusedField(array $data)
{
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            try {
                return findFocusedField($v);
            } catch (\Throwable $exception) {
                // it's ok, do nothing
            }
        } elseif ($k === 'focused') {
            return $v;
        }
    }
    throw new RuntimeException('Focused field not found!');
}

function findFieldWithEvent(array $data, $event)
{
    foreach ($data as $k => $v) {
        if ($k === 'eventListeners' && is_array($v) && in_array($event, $v)) {
            return $data['id'];
        } elseif (is_array($v)) {
            try {
                return findFieldWithEvent($v, $event);
            } catch (\Throwable $exception) {
                // it's ok, do nothing
            }
        }
    }
    throw new RuntimeException("Event {$event} not found!");
}

function findFieldByText(array $data, $text)
{
    foreach ($data as $k => $v) {
        if ($k === 'v' && isset($v['text']) && $v['text'] == $text) {
            return $data['id'];
        } elseif (is_array($v)) {
            try {
                return findFieldByText($v, $text);
            } catch (\Throwable $exception) {
                // it's ok, do nothing
            }
        }
    }
    throw new RuntimeException("Text {$text} not found!");
}