#!/usr/bin/env python2.7

import os
import signal
import subprocess
import threading
import time
import urllib2
import json
import base64


def main():
    # test_koala_go()
    test_ld_preload()
    # test_java()


def test_koala_go():
    GOBIN = os.path.abspath(os.curdir) + '/bin'
    env = os.environ.copy()
    env['GOBIN'] = GOBIN
    shell_execute(
        'koala-go.sh install -tags="koala_go koala_replayer koala_recorder" '
        'github.com/didi/rdebug/koala/test/server', env=env)
    env = os.environ.copy()
    env['GOTRACEBACK'] = 'all'
    server = subprocess.Popen(
        [
            GOBIN + '/server'
        ],
        env=env, stdout=subprocess.PIPE)
    time.sleep(1)
    replay()
    time.sleep(1)
    print('send SIGTERM')
    server.send_signal(signal.SIGTERM)
    print(server.communicate()[0])


def test_java():
    env = os.environ.copy()
    env['CGO_CFLAGS'] = '-DKOALA_LIBC_NETWORK_HOOK -DKOALA_LIBC_FILE_HOOK -DKOALA_LIBC_TIME_HOOK'
    env['CGO_CPPFLAGS'] = env['CGO_CFLAGS']
    shell_execute(
        'go install -tags="koala_recorder koala_tracer" -buildmode=c-shared '
        'github.com/didi/rdebug/koala/cmd/replayer', env=env)
    shell_execute(
        'go build -tags="koala_recorder koala_tracer" -buildmode=c-shared -o koala-replayer.so '
        'github.com/didi/rdebug/koala/cmd/replayer', env=env)
    shell_execute('javac java/Server.java')
    env = os.environ.copy()
    # env['LD_DEBUG'] = 'bindings'
    env['LD_PRELOAD'] = '%s/koala-replayer.so' % os.path.abspath('.')
    env['GOTRACEBACK'] = 'all'
    server = subprocess.Popen(
        [
            # 'strace', '-f',
            'java', '-cp', 'java', 'Server'
        ],
        env=env,
    )
    time.sleep(1)

    # print('send SIGTERM')
    # server.send_signal(signal.SIGTERM)
    # print(server.communicate()[0])
    # return

    thread1 = threading.Thread(target=call_server)
    thread1.start()
    thread1.join()
    # thread2 = threading.Thread(target=replay)
    # thread2.start()
    # thread2.join()
    time.sleep(1)
    # print('send SIGTERM')
    # server.send_signal(signal.SIGTERM)
    print(server.communicate()[0])

def test_ld_preload():
    env = os.environ.copy()
    env['CGO_CFLAGS'] = '-DKOALA_LIBC_NETWORK_HOOK'
    env['CGO_CPPFLAGS'] = env['CGO_CFLAGS']
    shell_execute(
        'go install -tags="koala_recorder koala_tracer" -buildmode=c-shared '
        'github.com/didi/rdebug/koala/cmd/recorder', env=env)
    shell_execute(
        'go build -tags="koala_recorder koala_tracer" -buildmode=c-shared -o koala-recorder.so '
        'github.com/didi/rdebug/koala/cmd/recorder', env=env)
    env = os.environ.copy()
    env['LD_PRELOAD'] = '%s/koala-recorder.so' % os.path.abspath('.')
    if not os.path.exists('/tmp/sessions'):
        os.mkdir('/tmp/sessions')
    env['KOALA_RECORD_TO_DIR'] = '/tmp/sessions'
    env['SERVER_MODE'] = 'MULTI_THREADS'
    env['GOTRACEBACK'] = 'all'
    server = subprocess.Popen(
        [
            # 'strace', '-e', 'trace=network',
            'python', 'server.py'
        ],
        env=env,
    )
    time.sleep(1)

    # print('send SIGTERM')
    # server.send_signal(signal.SIGTERM)
    # print(server.communicate()[0])
    # return

    thread1 = threading.Thread(target=call_server)
    thread1.start()
    thread1.join()
    # thread2 = threading.Thread(target=replay)
    # thread2.start()
    # thread2.join()
    time.sleep(1)
    # print('send SIGTERM')
    # server.send_signal(signal.SIGTERM)
    print(server.communicate()[0])


def shell_execute(cmd, **kwargs):
    print(cmd)
    subprocess.check_call(cmd, shell=True, **kwargs)


def call_server():
    print(urllib2.urlopen('http://127.0.0.1:2515').read())


def replay():
    resp = urllib2.urlopen('http://127.0.0.1:2514/json', data="""
{
  "CallFromInbound": {
    "ActionId": "1502583648166694263",
    "OccurredAt": 1502583648166694263,
    "ActionType": "CallFromInbound",
    "Peer": {
      "IP": "127.0.0.1",
      "Port": 37360,
      "Zone": ""
    },
    "Request": "R0VUIC8gSFRUUC8xLjENCkFjY2VwdC1FbmNvZGluZzogaWRlbnRpdHkNCkhvc3Q6IDEyNy4wLjAuMTo5MDAwDQpDb25uZWN0aW9uOiBjbG9zZQ0KVXNlci1BZ2VudDogUHl0aG9uLXVybGxpYi8yLjcNCg0K"
  },
  "ReturnInbound": {
    "ActionId": "1502583648218118763",
    "OccurredAt": 1502583648218118763,
    "ActionType": "ReturnInbound",
    "Response": "Z29vZCBkYXk="
  },
  "CallOutbounds": [
    {
      "ActionId": "1502583648179667868",
      "OccurredAt": 1502583648179667868,
      "ActionType": "CallOutbound",
      "Peer": {
        "IP": "111.206.223.205",
        "Port": 80,
        "Zone": ""
      },
      "Request": "R0VUIC8gSFRUUC8xLjENCkhvc3Q6IHd3dy5iYWlkdS5jb20NCkNvbm5lY3Rpb246IGtlZXAtYWxpdmUNCkFjY2VwdC1FbmNvZGluZzogZ3ppcCwgZGVmbGF0ZQ0KQWNjZXB0OiAqLyoNClVzZXItQWdlbnQ6IHB5dGhvbi1yZXF1ZXN0cy8yLjE4LjMNCg0K",
      "ResponseTime": 1502583648187122110,
      "Response": "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"
    },
    {
      "ActionId": "1502583648197354188",
      "OccurredAt": 1502583648197354188,
      "ActionType": "CallOutbound",
      "Peer": {
        "IP": "111.206.223.205",
        "Port": 80,
        "Zone": ""
      },
      "Request": "R0VUIC8gSFRUUC8xLjENCkhvc3Q6IHd3dy5iYWlkdS5jb20NCkNvbm5lY3Rpb246IGtlZXAtYWxpdmUNCkFjY2VwdC1FbmNvZGluZzogZ3ppcCwgZGVmbGF0ZQ0KQWNjZXB0OiAqLyoNClVzZXItQWdlbnQ6IHB5dGhvbi1yZXF1ZXN0cy8yLjE4LjMNCkNvb2tpZTogQkRPUlo9MjczMTUNCg0K",
      "ResponseTime": 1502583648215846817,
      "Response": "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"
    }
  ]
}
        """).read()
    print(base64.decodestring(json.loads(resp)['ReturnInbound']['Response']))


main()
