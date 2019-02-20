#ifndef __ALLOCATED_STRING_H__
#define __ALLOCATED_STRING_H__

#include <stddef.h>

struct ch_allocated_string {
    char *Ptr;
    size_t Len;
};

#endif
