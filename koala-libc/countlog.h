#ifndef __COUNTLOG_H__
#define __COUNTLOG_H__

# include <pthread.h>

enum event_level { trace=10, debug=20, info = 30, warn = 40, error = 50, fatal = 60 };
enum event_arg_type { STRING=1, UNSIGNED_LONG=2 };

struct event_arg {
    enum event_arg_type Type;
    const char *Val_string;
    unsigned long Val_ulong;
};

struct event_arg cl_str(const char *val) {
    struct event_arg _ = { STRING, val, strlen(val) };
    return _;
}

struct event_arg cl_ulong(unsigned long val) {
    struct event_arg _ = { UNSIGNED_LONG, 0, val };
    return _;
}

typedef void (*countlog0_pfn_t)(pid_t, int, struct ch_span);
static countlog0_pfn_t countlog0_func = NULL;

typedef void (*countlog1_pfn_t)(pid_t, int, struct ch_span, struct ch_span, struct event_arg);
static countlog1_pfn_t countlog1_func = NULL;

void load_koala_so_countlog(void *koala_so_handle) {
    countlog0_func = (countlog0_pfn_t) dlsym(koala_so_handle, "countlog0");
    countlog1_func = (countlog1_pfn_t) dlsym(koala_so_handle, "countlog1");
}

static __thread pid_t _thread_id = 0;

static pid_t get_thread_id() {
    if (_thread_id == 0) {
#ifdef __APPLE__
        uint64_t tid;
        pthread_threadid_np(NULL, &tid);
        _thread_id = (pid_t)tid;
#else
        _thread_id = syscall(__NR_gettid);
#endif
    }
    return _thread_id;
}

static void countlog0(enum event_level level, const char *event) {
    if (countlog0_func == NULL) {
        return;
    }
    struct ch_span event_span;
    event_span.Ptr = event;
    event_span.Len = strlen(event);
    countlog0_func(get_thread_id(), level, event_span);
}

static void countlog1(enum event_level level, const char *event, const char *k1, struct event_arg v1) {
    if (countlog1_func == NULL) {
        return;
    }
    struct ch_span event_span;
    event_span.Ptr = event;
    event_span.Len = strlen(event);
    struct ch_span k1_span;
    k1_span.Ptr = k1;
    k1_span.Len = strlen(k1);
    countlog1_func(get_thread_id(), level, event_span, k1_span, v1);
}

#endif
