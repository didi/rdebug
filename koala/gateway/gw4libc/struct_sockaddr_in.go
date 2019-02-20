package gw4libc

// #include <stddef.h>
// #include <netinet/in.h>
// #include <sys/types.h>
// #include <sys/socket.h>
import "C"
import (
	"reflect"
	"unsafe"
	"github.com/didi/rdebug/koala/ch"
	"runtime"
)

var sockaddr_in_type = reflect.TypeOf((*C.struct_sockaddr_in)(nil)).Elem()
var sockaddr_in_sin_family_field = ch.FieldOf(sockaddr_in_type, "sin_family")
var sockaddr_in_sin_port_field = ch.FieldOf(sockaddr_in_type, "sin_port")
var sockaddr_in_sin_addr_field = ch.FieldOf(sockaddr_in_type, "sin_addr")
var in_addr_type = reflect.TypeOf((*C.struct_in_addr)(nil)).Elem()
var in_addr_s_addr_field = ch.FieldOf(in_addr_type, "s_addr")

var sockaddr_in6_type = reflect.TypeOf((*C.struct_sockaddr_in6)(nil)).Elem()
var sockaddr_in6_sin_family_field = ch.FieldOf(sockaddr_in6_type, "sin6_family")
var sockaddr_in6_sin_port_field = ch.FieldOf(sockaddr_in6_type, "sin6_port")
var sockaddr_in6_sin_addr_field = ch.FieldOf(sockaddr_in6_type, "sin6_addr")
var in6_addr_type = reflect.TypeOf((*C.struct_in6_addr)(nil)).Elem()
var in6_addr_s_addr_field *reflect.StructField

func init() {
	if runtime.GOOS == "linux" {
		in6_addr_s_addr_field = ch.FieldOf(in6_addr_type, "__in6_u")
	} else {
		in6_addr_s_addr_field = ch.FieldOf(in6_addr_type, "__u6_addr")
	}
	//ch.Dump(in6_addr_type)
	//ch.Dump(ch.FieldOf(sockaddr_in_type, "sin_addr").Type)
}

func sockaddr_in_sin_family_get(ptr *C.struct_sockaddr_in) uint16 {
	if sockaddr_in_sin_family_field.Type.Kind() == reflect.Uint16 {
		return ch.GetUint16(unsafe.Pointer(ptr), sockaddr_in_sin_family_field)
	} else {
		return uint16(ch.GetUint8(unsafe.Pointer(ptr), sockaddr_in_sin_family_field))
	}
}

func sockaddr_in6_sin_family_get(ptr *C.struct_sockaddr_in6) uint16 {
	if sockaddr_in6_sin_family_field.Type.Kind() == reflect.Uint16 {
		return ch.GetUint16(unsafe.Pointer(ptr), sockaddr_in6_sin_family_field)
	} else {
		return uint16(ch.GetUint8(unsafe.Pointer(ptr), sockaddr_in6_sin_family_field))
	}
}

func sockaddr_in_sin_port_get(ptr *C.struct_sockaddr_in) uint16 {
	return ch.GetUint16(unsafe.Pointer(ptr), sockaddr_in_sin_port_field)
}

func sockaddr_in6_sin_port_get(ptr *C.struct_sockaddr_in6) uint16 {
	return ch.GetUint16(unsafe.Pointer(ptr), sockaddr_in6_sin_port_field)
}

func sockaddr_in_sin_port_set(ptr *C.struct_sockaddr_in, port uint16) {
	ch.SetUint16(unsafe.Pointer(ptr), sockaddr_in_sin_port_field, port)
}

func sockaddr_in6_sin_port_set(ptr *C.struct_sockaddr_in6, port uint16) {
	ch.SetUint16(unsafe.Pointer(ptr), sockaddr_in6_sin_port_field, port)
}

func sockaddr_in_sin_addr_get(ptr *C.struct_sockaddr_in) uint32 {
	sin_addr := ch.GetPtr(unsafe.Pointer(ptr), sockaddr_in_sin_addr_field)
	return ch.GetUint32(sin_addr, in_addr_s_addr_field)
}

func sockaddr_in6_sin_addr_get(ptr *C.struct_sockaddr_in6) [16]byte {
	sin_addr := ch.GetPtr(unsafe.Pointer(ptr), sockaddr_in6_sin_addr_field)
	return ch.Get16ElementsByteArray(sin_addr, in6_addr_s_addr_field)
}

func sockaddr_in_sin_addr_set(ptr *C.struct_sockaddr_in, ip uint32) {
	sin_addr := ch.GetPtr(unsafe.Pointer(ptr), sockaddr_in_sin_addr_field)
	ch.SetUint32(sin_addr, in_addr_s_addr_field, ip)
}

func sockaddr_in6_sin_addr_set(ptr *C.struct_sockaddr_in6, ip [16]byte) {
	sin_addr := ch.GetPtr(unsafe.Pointer(ptr), sockaddr_in6_sin_addr_field)
	ch.Set16ElementsByteArray(sin_addr, in6_addr_s_addr_field, ip)
}
