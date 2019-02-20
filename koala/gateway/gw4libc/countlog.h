#ifndef __COUNTLOG_H__
#define __COUNTLOG_H__

enum event_level { trace=10, debug=20, info = 30, warn = 40, error = 50, fatal = 60 };
enum event_arg_type { STRING=1, UNSIGNED_LONG=2 };

struct event_arg {
    enum event_arg_type Type;
    const char *Val_string;
    unsigned long Val_ulong;
};

#endif
