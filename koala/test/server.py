import BaseHTTPServer
import SocketServer
import socket
import threading
import requests
import os
import datetime

PORT = 2515

logFile = open('/tmp/server.log', 'a')

sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)

class MyHandler(BaseHTTPServer.BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/':
            sock.sendto('to-koala!set-trace-header-key\norder_id\nworld', 0, ('127.127.127.127', 127))
            s = requests.Session()
            s.get('http://127.0.0.1:2515/branch')
            self.send_response(200)
            self.wfile.write(str(datetime.datetime.now()))
        if self.path == '/branch':
            sock.sendto('to-koala!get-trace-header\n', 0, ('127.127.127.127', 127))
            trace_header = sock.recvfrom(4096, 127127)[0]
            s = requests.Session()
            s.get('http://127.0.0.1:2515/leaf1')
            s.get('http://127.0.0.1:2515/leaf2')
            self.send_response(200)
            self.wfile.write(trace_header)
        elif self.path == '/leaf1':
            sock.sendto('to-koala!get-trace-header-key\norder_id', 0, ('127.127.127.127', 127))
            order_id = sock.recvfrom(4096, 127127)[0]
            self.send_response(200)
            self.wfile.write(order_id)
        elif self.path == '/leaf2':
            self.send_response(200)
            self.wfile.write('leaf2')


class ThreadingMixIn:
    """Mix-in class to handle each request in a new thread."""

    # Decides how threads will act upon termination of the
    # main process
    daemon_threads = False

    def process_request_thread(self, request, client_address):
        """Same as in BaseServer but as a thread.

        In addition, exception handling is done here.

        """
        try:
            try:
                self.finish_request(request, client_address)
                self.shutdown_request(request)
            except:
                self.handle_error(request, client_address)
                self.shutdown_request(request)
        finally:
            sock.sendto(
                'to-koala!thread-shutdown\n',
                ('127.127.127.127', 127))

    def process_request(self, request, client_address):
        """Start a new thread to process the request."""
        t = threading.Thread(target=self.process_request_thread,
                             args=(request, client_address))
        t.daemon = self.daemon_threads
        t.start()


class ThreadedTCPServer(ThreadingMixIn, SocketServer.TCPServer):
    pass


SocketServer.TCPServer.allow_reuse_address = True
if os.getenv('SERVER_MODE') == 'MULTI_THREADS':
    httpd = ThreadedTCPServer(("", PORT), MyHandler)
else:
    httpd = SocketServer.TCPServer(("", PORT), MyHandler)

print "serving at port", PORT
httpd.serve_forever()
