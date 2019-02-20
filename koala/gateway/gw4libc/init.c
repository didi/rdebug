static int _init;

void go_initialized() {
    _init = 1;
}

int is_go_initialized() {
    return _init;
}

