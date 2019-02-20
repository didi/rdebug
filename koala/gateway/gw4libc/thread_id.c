#define _GNU_SOURCE

#include <unistd.h>
#include <pthread.h>
#include <sys/syscall.h>
#include "thread_id.h"

pid_t get_thread_id() {
#ifdef __APPLE__
    uint64_t tid;
    pthread_threadid_np(NULL, &tid);
    return (pid_t)tid;
#else
    return syscall(__NR_gettid);
#endif
}
